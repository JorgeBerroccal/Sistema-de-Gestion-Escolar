<?php

// Recorrido automatizado de quickstart.md §2 con los componentes reales.

use App\Livewire\Asistencia\Propia\Index as Mia;
use App\Livewire\Asistencia\Reportes\Index as Reportes;
use App\Livewire\Asistencia\Tomar\Index as Tomar;
use App\Livewire\Asistencia\Tomar\Show;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use Livewire\Livewire;

it('recorre la prueba de humo: toma, fin de semana, justificación, reporte y rol limitado', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'admin' => $admin] = escenarioAsistencia(3);
    $dia = diaHabilReciente()->toDateString();
    $falta = EstadoAsistencia::where('codigo', 'falta')->value('id');
    $tarde = EstadoAsistencia::where('codigo', 'tarde')->value('id');

    // 1. Menú y lista con todos «presente».
    $this->actingAs($admin)->get('/inicio')->assertOk()->assertSee('Tomar asistencia');
    Livewire::actingAs($admin)->test(Tomar::class)->assertSee($seccion->identificador);
    $lista = Livewire::actingAs($admin)->test(Show::class, ['seccion' => $seccion, 'fecha' => $dia]);
    expect(collect($lista->get('filas'))->pluck('estado_id')->unique()->all())->toBe([EstadoAsistencia::where('codigo', 'presente')->value('id')]);

    // 2. Una falta y una tardanza; guardar y reabrir.
    $lista->set("filas.{$alumnos[0]->id}.estado_id", $falta)->set("filas.{$alumnos[1]->id}.estado_id", $tarde)->call('guardar')->assertHasNoErrors();
    $reabierta = Livewire::actingAs($admin)->test(Show::class, ['seccion' => $seccion, 'fecha' => $dia]);
    expect($reabierta->get('filas')[$alumnos[0]->id]['estado_id'])->toBe($falta);

    // 3. Un sábado: aviso y sin guardar.
    $sabado = diaHabilReciente()->previous('saturday')->toDateString();
    Livewire::actingAs($admin)->test(Show::class, ['seccion' => $seccion, 'fecha' => $sabado])->assertSee('sábados ni los domingos')->assertDontSee('Guardar asistencia');

    // 4. Justificar la falta con motivo.
    $registro = Asistencia::where('alumno_id', $alumnos[0]->id)->first();
    $reabierta->call('iniciarJustificacion', $registro->id)->set('motivo', 'Certificado médico')->call('justificar')->assertHasNoErrors();
    expect($registro->fresh()->estado->codigo)->toBe('falta_justificada');

    // 5. Reporte coherente: 1 presente, 1 tarde, 1 justificada → 100 % (la justificada no entra).
    Livewire::actingAs($admin)->test(Reportes::class)->set('seccion', (string) $seccion->id)->set('desde', $dia)->set('hasta', $dia)
        ->assertViewHas('resumen', fn ($r) => $r['registrados'] === 3 && $r['porcentaje'] === 100.0);

    // 6. El padre ve solo a su hijo, con resumen y sin controles.
    $padre = usuarioConPermisos(['asistencia.ver_propia']);
    $alumnos[0]->apoderados()->attach($padre->id, ['parentesco' => 'madre']);
    Livewire::actingAs($padre)->test(Mia::class)->assertSee('Falta justificada')->assertDontSee('Guardar asistencia')->assertDontSee('Justificar falta');

    // 7. Lo ajeno da 403.
    $this->actingAs($padre)->get(route('asistencia.tomar.show', $seccion))->assertForbidden();
    $this->actingAs($padre)->get(route('asistencia.reportes.index'))->assertForbidden();
});
