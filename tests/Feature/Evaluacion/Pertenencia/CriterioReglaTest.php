<?php

use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Matricula;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('ver_todas ve todos los criterios', function () {
    CriterioEvaluacion::factory()->count(3)->create();

    expect(CriterioEvaluacion::query()->visiblePara(usuarioConPermisos(['notas.ver_todas']))->count())->toBe(3);
});

it('el docente ve solo los criterios de los pares (sección, curso) con su asignación', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'docente' => $docente, 'periodo' => $periodo] = escenarioEvaluacion(1);
    CriterioEvaluacion::factory()->count(2)->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    // Otro curso de la misma sección y otra sección con el mismo curso: no son suyos.
    CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    CriterioEvaluacion::factory()->create(['curso_id' => $curso->id, 'periodo_id' => $periodo->id]);

    expect(CriterioEvaluacion::query()->visiblePara($docente)->count())->toBe(2);
});

it('con asignación pero sin permiso no ve nada', function () {
    $criterio = CriterioEvaluacion::factory()->create();
    $docente = usuarioConPermisos([]);
    AsignacionDocente::factory()->create(['seccion_id' => $criterio->seccion_id, 'curso_id' => $criterio->curso_id, 'usuario_id' => $docente->id]);

    expect(CriterioEvaluacion::query()->visiblePara($docente)->count())->toBe(0);
});

it('la familia ve criterios solo de periodos cerrados y de secciones donde su hijo está matriculado', function () {
    ['seccion' => $seccion, 'periodo' => $periodo, 'curso' => $curso, 'alumnos' => $alumnos] = escenarioEvaluacion(1);
    $hijo = $alumnos->first();
    $criterio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $ajeno = CriterioEvaluacion::factory()->create(); // otra sección donde el hijo no está
    CierrePeriodo::factory()->create(['seccion_id' => $ajeno->seccion_id, 'periodo_id' => $ajeno->periodo_id]);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    expect(CriterioEvaluacion::query()->visiblePara($padre)->count())->toBe(0); // sin cierre: no publicado

    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);

    expect(CriterioEvaluacion::query()->visiblePara($padre)->pluck('id')->all())->toBe([$criterio->id]);
});

it('un alumno retirado de la sección conserva la visibilidad de sus criterios publicados', function () {
    ['seccion' => $seccion, 'periodo' => $periodo, 'alumnos' => $alumnos] = escenarioEvaluacion(1);
    $hijo = $alumnos->first();
    Matricula::where('alumno_id', $hijo->id)->update(['estado' => 'retirado', 'fecha_estado' => now()->toDateString()]);
    CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'madre']);

    expect(CriterioEvaluacion::query()->visiblePara($padre)->count())->toBe(1);
});

it('usuario sin vínculos o sin ningún permiso no ve nada', function () {
    ['seccion' => $seccion, 'periodo' => $periodo] = escenarioEvaluacion(1);
    CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);

    expect(CriterioEvaluacion::query()->visiblePara(usuarioConPermisos(['notas.ver_propia']))->count())->toBe(0)
        ->and(CriterioEvaluacion::query()->visiblePara(usuarioConPermisos([]))->count())->toBe(0);
});

it('permite() coincide con el filtro y autorizarPertenencia lanza 403 si no corresponde', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente] = escenarioEvaluacion(1);
    $propio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $otro = CriterioEvaluacion::factory()->create();

    $propio->autorizarPertenencia($docente);
    expect(fn () => $otro->autorizarPertenencia($docente))->toThrow(AccessDeniedHttpException::class);
});
