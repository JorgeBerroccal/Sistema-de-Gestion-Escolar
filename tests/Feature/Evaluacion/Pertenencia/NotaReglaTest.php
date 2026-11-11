<?php

use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Nota;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('ver_todas ve todas las notas', function () {
    Nota::factory()->count(3)->create();

    expect(Nota::query()->visiblePara(usuarioConPermisos(['notas.ver_todas']))->count())->toBe(3);
});

it('el docente ve solo las notas de sus pares (sección, curso)', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'alumnos' => $alumnos] = escenarioEvaluacion(2);
    $propio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $otroCurso = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    foreach ($alumnos as $a) {
        Nota::factory()->create(['criterio_id' => $propio->id, 'alumno_id' => $a->id]);
        Nota::factory()->create(['criterio_id' => $otroCurso->id, 'alumno_id' => $a->id]);
    }

    expect(Nota::query()->visiblePara($docente)->count())->toBe(2)
        ->and(Nota::query()->visiblePara($docente)->pluck('criterio_id')->unique()->all())->toBe([$propio->id]);
});

it('con asignación pero sin permiso no ve nada', function () {
    $nota = Nota::factory()->create();
    $docente = usuarioConPermisos([]);
    AsignacionDocente::factory()->create(['seccion_id' => $nota->criterio->seccion_id, 'curso_id' => $nota->criterio->curso_id, 'usuario_id' => $docente->id]);

    expect(Nota::query()->visiblePara($docente)->count())->toBe(0);
});

it('la familia ve solo las notas de sus hijos y solo de periodos cerrados', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'alumnos' => $alumnos] = escenarioEvaluacion(2);
    [$hijo, $ajeno] = $alumnos->all();
    $criterio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $notaHijo = Nota::factory()->create(['criterio_id' => $criterio->id, 'alumno_id' => $hijo->id]);
    Nota::factory()->create(['criterio_id' => $criterio->id, 'alumno_id' => $ajeno->id]);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    expect(Nota::query()->visiblePara($padre)->count())->toBe(0); // periodo sin cierre

    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);

    expect(Nota::query()->visiblePara($padre)->pluck('id')->all())->toBe([$notaHijo->id]);

    CierrePeriodo::query()->delete(); // reabrir
    expect(Nota::query()->visiblePara($padre)->count())->toBe(0);
});

it('el alumno con usuario vinculado ve solo las suyas', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'alumnos' => $alumnos] = escenarioEvaluacion(2);
    [$yo, $otro] = $alumnos->all();
    $criterio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $mia = Nota::factory()->create(['criterio_id' => $criterio->id, 'alumno_id' => $yo->id]);
    Nota::factory()->create(['criterio_id' => $criterio->id, 'alumno_id' => $otro->id]);
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    $usuario = usuarioConPermisos(['notas.ver_propia']);
    $yo->update(['usuario_id' => $usuario->id]);

    expect(Nota::query()->visiblePara($usuario)->pluck('id')->all())->toBe([$mia->id]);
});

it('usuario sin vínculos o sin permisos no ve nada, y permite() coincide con el filtro', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'alumnos' => $alumnos] = escenarioEvaluacion(1);
    $criterio = CriterioEvaluacion::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id]);
    $propia = Nota::factory()->create(['criterio_id' => $criterio->id, 'alumno_id' => $alumnos->first()->id]);
    $otra = Nota::factory()->create();
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);

    expect(Nota::query()->visiblePara(usuarioConPermisos(['notas.ver_propia']))->count())->toBe(0)
        ->and(Nota::query()->visiblePara(usuarioConPermisos([]))->count())->toBe(0);

    $propia->autorizarPertenencia($docente);
    expect(fn () => $otra->autorizarPertenencia($docente))->toThrow(AccessDeniedHttpException::class);
});
