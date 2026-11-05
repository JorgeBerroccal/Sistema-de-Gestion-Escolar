<?php

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Seccion;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('ver_todas ve toda la asistencia', function () {
    Asistencia::factory()->count(3)->create();

    expect(Asistencia::query()->visiblePara(usuarioConPermisos(['asistencia.ver_todas']))->count())->toBe(3);
});

it('el docente ve solo las secciones donde tiene una asignación', function () {
    $propia = Seccion::factory()->create();
    Asistencia::factory()->count(2)->create(['seccion_id' => $propia->id]);
    Asistencia::factory()->create();
    $docente = usuarioConPermisos(['asistencia.registrar_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $propia->id, 'usuario_id' => $docente->id]);

    expect(Asistencia::query()->visiblePara($docente)->count())->toBe(2);
});

it('con asignación pero sin permiso no ve nada', function () {
    $seccion = Seccion::factory()->create();
    Asistencia::factory()->create(['seccion_id' => $seccion->id]);
    $docente = usuarioConPermisos([]);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $docente->id]);

    expect(Asistencia::query()->visiblePara($docente)->count())->toBe(0);
});

it('el padre y el alumno ven solo la asistencia de sus hijos o propia', function () {
    [$hijo, $ajeno] = Alumno::factory()->count(2)->create();
    Asistencia::factory()->create(['alumno_id' => $hijo->id]);
    Asistencia::factory()->create(['alumno_id' => $ajeno->id]);
    $padre = usuarioConPermisos(['asistencia.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);
    $sinVinculos = usuarioConPermisos(['asistencia.ver_propia']);

    expect(Asistencia::query()->visiblePara($padre)->pluck('alumno_id')->all())->toBe([$hijo->id])
        ->and(Asistencia::query()->visiblePara($sinVinculos)->count())->toBe(0);
});

it('permite() coincide con el filtro y autorizarPertenencia lanza 403 si no corresponde', function () {
    $registro = Asistencia::factory()->create();
    $otro = Asistencia::factory()->create();
    $padre = usuarioConPermisos(['asistencia.ver_propia']);
    $registro->alumno->apoderados()->attach($padre->id, ['parentesco' => 'madre']);

    $registro->autorizarPertenencia($padre);
    expect(fn () => $otro->autorizarPertenencia($padre))->toThrow(AccessDeniedHttpException::class);
});

it('un usuario sin ningún permiso de asistencia no ve nada', function () {
    Asistencia::factory()->create();

    expect(Asistencia::query()->visiblePara(usuarioConPermisos([]))->count())->toBe(0);
});
