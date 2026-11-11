<?php

use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Livewire\Evaluacion\Registro\Index;
use App\Livewire\Evaluacion\Registro\Show;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Permiso;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('el docente con asignación abre el par y guarda; sin asignación recibe 403 al abrir y al guardar', function () {
    $e = escenarioEvaluacion(2);
    $otroCurso = Curso::factory()->create();
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();

    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])->assertOk();
    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $otroCurso])->assertForbidden();

    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$e['alumnos']->first()->id => [$a->id => '15']], $e['docente']);

    expect(fn () => app(EvaluacionService::class)->guardarNotas($e['seccion'], $otroCurso, $e['periodo'], [], $e['docente']))
        ->toThrow(AccessDeniedHttpException::class);
});

it('con el periodo cerrado el docente no guarda y el Administrador sí', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);
    $celdas = [$e['alumnos']->first()->id => [$a->id => '17']];
    $servicio = app(EvaluacionService::class);

    expect(fn () => $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $e['docente']))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $servicio->guardarEsquema($e['seccion'], $e['curso'], $e['periodo'], [['nombre' => 'Otro', 'peso' => 100]], $e['docente']))->toThrow(EvaluacionIncumplida::class);

    $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $e['admin']);

    // El docente ve la tabla en solo lectura.
    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])
        ->assertSee('Periodo cerrado')->assertDontSee('Guardar notas');
});

it('con un año que ya no es el vigente nadie guarda', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    AnioEscolar::whereKey($e['anio']->id)->update(['vigente' => null]);

    expect(fn () => app(EvaluacionService::class)->guardarNotas($e['seccion']->fresh(), $e['curso'], $e['periodo'], [$e['alumnos']->first()->id => [$a->id => '10']], $e['admin']))
        ->toThrow(EvaluacionIncumplida::class);
});

it('el Administrador abre cualquier par y lo ve en el índice; el docente solo el suyo', function () {
    $e = escenarioEvaluacion(1);
    $otroCurso = Curso::factory()->create(['nivel_id' => $e['curso']->nivel_id]);
    $e['seccion']->grado->cursos()->attach([$e['curso']->id, $otroCurso->id]);

    Livewire::actingAs($e['admin'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $otroCurso])->assertOk();
    expect(Livewire::actingAs($e['admin'])->test(Index::class)->viewData('pares')->total())->toBe(2)
        ->and(Livewire::actingAs($e['docente'])->test(Index::class)->viewData('pares')->total())->toBe(1);
});

it('Director, Padre y Alumno reciben 403 en el registro', function () {
    $e = escenarioEvaluacion(1);

    foreach ([['notas.ver_todas'], ['notas.ver_propia'], []] as $permisos) {
        $u = usuarioConPermisos($permisos);
        Livewire::actingAs($u)->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])->assertForbidden();
        Livewire::actingAs($u)->test(Index::class)->assertForbidden();
    }
});

it('un rol creado en la prueba con registrar_propia funciona y sin el permiso no', function () {
    $e = escenarioEvaluacion(1);
    Livewire::actingAs($e['docente'])->test(Index::class)->assertOk();

    $e['docente']->rol->permisos()->detach(Permiso::where('identificador', 'notas.registrar_propia')->value('id'));

    Livewire::actingAs($e['docente']->fresh())->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])->assertForbidden();
});

it('el editor de criterios guarda y valida desde el componente', function () {
    $e = escenarioEvaluacion(1);

    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])
        ->set('criterios', [['id' => null, 'nombre' => 'Práctica', 'peso' => '70'], ['id' => null, 'nombre' => 'Examen', 'peso' => '40']])
        ->call('guardarCriterios')->assertHasErrors('criterios');

    expect(CriterioEvaluacion::count())->toBe(0);

    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])
        ->set('criterios', [['id' => null, 'nombre' => 'Práctica', 'peso' => '60'], ['id' => null, 'nombre' => 'Examen', 'peso' => '40']])
        ->call('guardarCriterios')->assertHasNoErrors()->assertRedirect();

    expect(CriterioEvaluacion::count())->toBe(2);
});
