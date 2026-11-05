<?php

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Matricula;
use App\Models\Seccion;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('ver_todas ve todas las matrículas', function () {
    Matricula::factory()->count(3)->create();

    expect(Matricula::query()->visiblePara(usuarioConPermisos(['matricula.ver_todas']))->count())->toBe(3);
});

it('el docente ve solo las matrículas de las secciones donde tiene una asignación', function () {
    $propia = Seccion::factory()->create();
    $ajena = Seccion::factory()->create();
    $mias = Matricula::factory()->count(2)->create(['seccion_id' => $propia->id]);
    Matricula::factory()->create(['seccion_id' => $ajena->id]);
    $docente = usuarioConPermisos(['matricula.ver_nomina_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $propia->id, 'usuario_id' => $docente->id]);

    expect(Matricula::query()->visiblePara($docente)->pluck('id')->sort()->values()->all())->toBe($mias->pluck('id')->sort()->values()->all());
});

it('el docente sin permiso de nómina no ve nada aunque tenga asignaciones', function () {
    $seccion = Seccion::factory()->create();
    Matricula::factory()->create(['seccion_id' => $seccion->id]);
    $docente = usuarioConPermisos([]);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $docente->id]);

    expect(Matricula::query()->visiblePara($docente)->count())->toBe(0);
});

it('el padre y el alumno ven las matrículas de sus hijos o propias', function () {
    [$hijo, $ajeno] = Alumno::factory()->count(2)->create();
    $m = Matricula::factory()->create(['alumno_id' => $hijo->id]);
    Matricula::factory()->create(['alumno_id' => $ajeno->id]);
    $padre = usuarioConPermisos(['matricula.ver_propia']);
    $hijo->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    expect(Matricula::query()->visiblePara($padre)->pluck('id')->all())->toBe([$m->id]);

    $alumno = usuarioConPermisos(['matricula.ver_propia']);
    $ajeno->update(['usuario_id' => $alumno->id]);
    expect(Matricula::query()->visiblePara($alumno)->count())->toBe(1);
});

it('combina con OR los permisos de docente y de familia', function () {
    $seccion = Seccion::factory()->create();
    $deSeccion = Matricula::factory()->create(['seccion_id' => $seccion->id]);
    $hijo = Alumno::factory()->create();
    $deHijo = Matricula::factory()->create(['alumno_id' => $hijo->id]);
    Matricula::factory()->create();
    $usuario = usuarioConPermisos(['matricula.ver_nomina_propia', 'matricula.ver_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $usuario->id]);
    $hijo->apoderados()->attach($usuario->id, ['parentesco' => 'tutor']);

    expect(Matricula::query()->visiblePara($usuario)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$deSeccion->id, $deHijo->id])->sort()->values()->all());
});

it('autorizarPertenencia deniega con 403 lo ajeno y sin permisos', function () {
    $seccion = Seccion::factory()->create();
    $propia = Matricula::factory()->create(['seccion_id' => $seccion->id]);
    $ajena = Matricula::factory()->create();
    $docente = usuarioConPermisos(['matricula.ver_nomina_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $docente->id]);

    $propia->autorizarPertenencia($docente);
    expect(fn () => $ajena->autorizarPertenencia($docente))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $propia->autorizarPertenencia(usuarioConPermisos([])))->toThrow(AccessDeniedHttpException::class);
});
