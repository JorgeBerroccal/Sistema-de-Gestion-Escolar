<?php

use App\Models\Alumno;
use App\Models\Usuario;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

function apoderadoDe(Alumno ...$alumnos): Usuario
{
    $padre = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado']);
    foreach ($alumnos as $alumno) {
        $alumno->apoderados()->attach($padre->id, ['parentesco' => 'madre']);
    }

    return $padre;
}

it('ver_todas ve todos los alumnos', function () {
    Alumno::factory()->count(3)->create();
    $director = usuarioConPermisos(['matricula.ver_todas']);

    expect(Alumno::query()->visiblePara($director)->count())->toBe(3);
});

it('ver_propia ve su ficha vinculada y la de sus hijos, y nada más', function () {
    [$hijo1, $hijo2, $ajeno] = Alumno::factory()->count(3)->create();
    $padre = apoderadoDe($hijo1, $hijo2);

    expect(Alumno::query()->visiblePara($padre)->pluck('id')->sort()->values()->all())->toBe([$hijo1->id, $hijo2->id]);

    $usuarioAlumno = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_alumno']);
    $ajeno->update(['usuario_id' => $usuarioAlumno->id]);

    expect(Alumno::query()->visiblePara($usuarioAlumno)->pluck('id')->all())->toBe([$ajeno->id]);
});

it('un usuario sin vínculos ve una lista vacía', function () {
    Alumno::factory()->count(2)->create();
    $sinVinculos = usuarioConPermisos(['matricula.ver_propia']);

    expect(Alumno::query()->visiblePara($sinVinculos)->count())->toBe(0);
});

it('sin ningún permiso de matrícula no ve nada (fail-closed), ni siquiera como docente', function () {
    Alumno::factory()->create();

    expect(Alumno::query()->visiblePara(usuarioConPermisos([]))->count())->toBe(0)
        ->and(Alumno::query()->visiblePara(usuarioConPermisos(['matricula.ver_nomina_propia']))->count())->toBe(0);
});

it('autorizarPertenencia permite lo propio y deniega lo ajeno con 403', function () {
    [$hijo, $ajeno] = Alumno::factory()->count(2)->create();
    $padre = apoderadoDe($hijo);

    $hijo->autorizarPertenencia($padre);
    expect(fn () => $ajeno->autorizarPertenencia($padre))->toThrow(AccessDeniedHttpException::class);
    expect(fn () => $hijo->autorizarPertenencia(usuarioConPermisos([])))->toThrow(AccessDeniedHttpException::class);
    $ajeno->autorizarPertenencia(usuarioConPermisos(['matricula.ver_todas']));
});

it('un usuario inactivo no ve nada aunque siga vinculado', function () {
    $hijo = Alumno::factory()->create();
    $padre = apoderadoDe($hijo);
    $padre->update(['activo' => false]);

    expect(Alumno::query()->visiblePara($padre->fresh())->count())->toBe(0);
});
