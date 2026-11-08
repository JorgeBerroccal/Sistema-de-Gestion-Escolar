<?php

use App\Livewire\Asistencia\Propia\Index;
use App\Models\Alumno;
use App\Models\Asistencia;
use Livewire\Livewire;

function padreConHijos(int $hijos = 2): array
{
    $padre = usuarioConPermisos(['asistencia.ver_propia']);
    $lista = Alumno::factory()->count($hijos)->create();
    $lista->each(fn ($h) => $h->apoderados()->attach($padre->id, ['parentesco' => 'padre']));

    return [$padre, $lista];
}

it('el padre ve a sus hijos, el resumen y el detalle del mes', function () {
    ['seccion' => $seccion] = escenarioAsistencia(0);
    [$padre, $hijos] = padreConHijos(2);
    $dia = diaHabilReciente()->toDateString();
    Asistencia::factory()->con('falta')->create(['seccion_id' => $seccion->id, 'alumno_id' => $hijos[0]->id, 'fecha' => $dia, 'observacion' => 'Cita médica']);

    Livewire::actingAs($padre)->test(Index::class, ['alumnoId' => (string) $hijos[0]->id])
        ->assertViewHas('alumnos', fn ($a) => $a->count() === 2)
        ->assertSee('Cita médica')->assertSee('Falta');
    Livewire::actingAs($padre)->test(Index::class, ['alumnoId' => (string) $hijos[1]->id])->assertOk()->assertDontSee('Cita médica');
});

it('el alumno ve solo la suya', function () {
    $alumno = Alumno::factory()->create();
    $usuario = usuarioConPermisos(['asistencia.ver_propia']);
    $alumno->update(['usuario_id' => $usuario->id]);
    Alumno::factory()->create();

    Livewire::actingAs($usuario)->test(Index::class)->assertViewHas('alumnos', fn ($a) => $a->pluck('id')->all() === [$alumno->id]);
});

it('un alumno ajeno por la dirección da 403', function () {
    [$padre] = padreConHijos(1);
    $ajeno = Alumno::factory()->create();

    Livewire::actingAs($padre)->test(Index::class, ['alumnoId' => (string) $ajeno->id])->assertForbidden();
});

it('un usuario sin vínculos ve la lista vacía y sin controles de edición', function () {
    $sinVinculos = usuarioConPermisos(['asistencia.ver_propia']);

    Livewire::actingAs($sinVinculos)->test(Index::class)->assertSee('No tienes alumnos vinculados')
        ->assertDontSee('Guardar asistencia')->assertDontSee('Justificar');
});

it('Director, Docente y usuarios sin permiso reciben 403', function () {
    foreach ([['asistencia.ver_todas'], ['asistencia.registrar_propia'], []] as $permisos) {
        Livewire::actingAs(usuarioConPermisos($permisos))->test(Index::class)->assertForbidden();
    }
});
