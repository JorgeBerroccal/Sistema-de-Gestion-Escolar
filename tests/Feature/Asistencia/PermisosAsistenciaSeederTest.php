<?php

use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;

function permisosAsistenciaDe(string $rol): array
{
    return Rol::where('nombre', $rol)->first()->permisos()->where('identificador', 'like', 'asistencia.%')
        ->pluck('identificador')->sort()->values()->all();
}

it('asigna por defecto los permisos de asistencia según el contrato', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(permisosAsistenciaDe('Administrador'))->toBe(['asistencia.gestionar', 'asistencia.ver_todas'])
        ->and(permisosAsistenciaDe('Director'))->toBe(['asistencia.ver_todas'])
        ->and(permisosAsistenciaDe('Docente'))->toBe(['asistencia.registrar_propia'])
        ->and(permisosAsistenciaDe('Padre de Familia'))->toBe(['asistencia.ver_propia'])
        ->and(permisosAsistenciaDe('Alumno'))->toBe(['asistencia.ver_propia']);
});

it('es idempotente y no pisa los cambios del Administrador', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Rol::where('nombre', 'Director')->first()->permisos()->detach(Permiso::where('identificador', 'asistencia.ver_todas')->value('id'));

    $this->seed(RolesYPermisosSeeder::class);

    expect(Permiso::where('identificador', 'like', 'asistencia.%')->count())->toBe(4)
        ->and(permisosAsistenciaDe('Director'))->toBeEmpty();
});
