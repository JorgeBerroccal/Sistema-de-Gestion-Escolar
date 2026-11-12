<?php

use App\Evaluacion\EvaluacionService;
use App\Livewire\Evaluacion\Propia\Index;
use App\Models\Alumno;
use App\Models\CierrePeriodo;
use App\Models\Matricula;
use App\Models\Nota;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Escenario: un alumno con notas 15/10 en un periodo; `$cerrar` publica el periodo. */
function escenarioNotasPropias(bool $cerrar = true, string $escala = 'vigesimal'): array
{
    $e = escenarioEvaluacion(2, $escala);
    $criterios = esquemaEvaluacion($e);
    [$hijo, $ajeno] = $e['alumnos']->all();
    $hijo->update(['apellidos' => 'Hijo', 'nombres' => 'Propio']);
    $ajeno->update(['apellidos' => 'Ajeno', 'nombres' => 'Reservado']);
    $valores = $escala === 'literal' ? ['A', 'B'] : ['15', '10'];
    foreach ([$hijo, $ajeno] as $alumno) {
        app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [
            $alumno->id => [$criterios[0]->id => $valores[0], $criterios[1]->id => $valores[1]],
        ], $e['admin']);
    }
    $cerrar && CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    return $e + ['hijo' => $hijo, 'ajeno' => $ajeno, 'padre' => $padre, 'criterios' => $criterios];
}

it('el Padre ve a su hijo con detalle y promedios, y a ningún otro alumno', function () {
    $s = escenarioNotasPropias();

    Livewire::actingAs($s['padre'])->test(Index::class)
        ->assertOk()->assertSee('Hijo, Propio')->assertDontSee('Reservado')
        ->assertSee($s['curso']->nombre)->assertSee('Práctica')->assertSee('Examen')
        ->assertSee('13,00'); // 15×0,6 + 10×0,4
});

it('el Padre con dos hijos elige entre ambos y no ve alumnos ajenos', function () {
    $s = escenarioNotasPropias();
    $s['ajeno']->apoderados()->attach($s['padre']->id, ['parentesco' => 'padre']);
    $otro = Alumno::factory()->create(['apellidos' => 'Tercero', 'nombres' => 'Ajeno']);
    Matricula::factory()->create(['alumno_id' => $otro->id, 'seccion_id' => $s['seccion']->id]);

    $c = Livewire::actingAs($s['padre'])->test(Index::class)->assertSee('Hijo, Propio')->assertSee('Ajeno, Reservado')->assertDontSee('Tercero');
    $c->set('hijo', $s['ajeno']->id)->assertOk();
});

it('el Alumno con usuario vinculado ve solo las suyas', function () {
    $s = escenarioNotasPropias();
    $usuario = usuarioConPermisos(['notas.ver_propia']);
    $s['ajeno']->update(['usuario_id' => $usuario->id]);

    Livewire::actingAs($usuario)->test(Index::class)->assertSee('Ajeno, Reservado')->assertDontSee('Hijo, Propio');
});

it('un alumno ajeno por la dirección o manipulando el componente recibe 403', function () {
    $s = escenarioNotasPropias();

    Livewire::actingAs($s['padre'])->withQueryParams(['hijo' => $s['ajeno']->id])->test(Index::class)->assertForbidden();

    // Manipular la propiedad del componente: el cambio de alumno se vuelve a autorizar.
    $this->actingAs($s['padre']);
    $componente = new Index;
    $componente->hijo = $s['ajeno']->id;
    expect(fn () => $componente->updatedHijo())->toThrow(AccessDeniedHttpException::class);

    $componente->hijo = $s['hijo']->id;
    $componente->updatedHijo(); // el propio sí se acepta

    $this->actingAs($s['padre'])->get('/notas/mias?hijo='.$s['ajeno']->id)->assertForbidden();
});

it('Padre y Alumno no pueden modificar notas: el guardado responde 403', function () {
    $s = escenarioNotasPropias();

    $this->actingAs($s['padre'])->post(
        route('notas.registro.guardar', ['seccion' => $s['seccion'], 'curso' => $s['curso']]),
        ['periodo' => $s['periodo']->id, 'notas' => [$s['hijo']->id => [$s['criterios'][0]->id => '20']]],
    )->assertForbidden();

    expect(Nota::where('alumno_id', $s['hijo']->id)->where('criterio_id', $s['criterios'][0]->id)->value('valor'))->toBe('15');
});

it('un periodo no publicado no muestra notas a la familia', function () {
    $s = escenarioNotasPropias(cerrar: false);

    Livewire::actingAs($s['padre'])->test(Index::class)->assertSee('Aún no hay periodos publicados')->assertDontSee('Práctica');

    CierrePeriodo::factory()->create(['seccion_id' => $s['seccion']->id, 'periodo_id' => $s['periodo']->id]);

    Livewire::actingAs($s['padre'])->test(Index::class)->assertSee('Práctica');

    CierrePeriodo::query()->delete(); // reabrir

    Livewire::actingAs($s['padre'])->test(Index::class)->assertSee('Aún no hay periodos publicados');
});

it('un usuario sin vínculos ve una lista vacía', function () {
    escenarioNotasPropias();

    Livewire::actingAs(usuarioConPermisos(['notas.ver_propia']))->test(Index::class)->assertOk()->assertSee('No tienes alumnos vinculados');
});

it('Docente, Director y un usuario sin permiso reciben 403', function () {
    $s = escenarioNotasPropias();

    foreach ([$s['docente'], $s['admin'], usuarioConPermisos(['notas.ver_todas']), usuarioConPermisos([])] as $usuario) {
        Livewire::actingAs($usuario)->test(Index::class)->assertForbidden();
    }
});

it('un rol creado en la prueba con notas.ver_propia funciona y sin el permiso no', function () {
    $s = escenarioNotasPropias();
    Livewire::actingAs($s['padre'])->test(Index::class)->assertOk();

    $s['padre']->rol->permisos()->detach();

    Livewire::actingAs($s['padre']->fresh())->test(Index::class)->assertForbidden();
});

it('con escala literal la familia ve las letras y el calificativo del promedio', function () {
    $s = escenarioNotasPropias(escala: 'literal'); // A (3) ×0,6 + B (2) ×0,4 = 2,6 → A

    Livewire::actingAs($s['padre'])->test(Index::class)->assertSee('Promedio del periodo: A');
});

it('el promedio anual aparece al elegir «Anual» y los periodos sin notas no cuentan como cero', function () {
    $s = escenarioNotasPropias();

    Livewire::actingAs($s['padre'])->test(Index::class)->set('periodo', 'anual')
        ->assertSee('Promedio anual')->assertSee('13,00');
});

it('la página responde 200 por HTTP para el Padre y muestra el menú «Mis notas»', function () {
    $s = escenarioNotasPropias();

    $this->actingAs($s['padre'])->get('/notas/mias')->assertOk()->assertSee('Hijo, Propio')->assertSee('Mis notas');
});
