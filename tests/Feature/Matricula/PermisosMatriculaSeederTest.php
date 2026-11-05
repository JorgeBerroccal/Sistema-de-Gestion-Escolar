<?php

use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;

function permisosMatriculaDe(string $rol): array
{
    return Rol::where('nombre', $rol)->first()->permisos()->where('identificador', 'like', 'matricula.%')
        ->pluck('identificador')->sort()->values()->all();
}

it('asigna por defecto los permisos de matrícula según el contrato', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(permisosMatriculaDe('Administrador'))->toBe(['matricula.gestionar', 'matricula.ver_todas'])
        ->and(permisosMatriculaDe('Director'))->toBe(['matricula.ver_todas'])
        ->and(permisosMatriculaDe('Docente'))->toBe(['matricula.ver_nomina_propia'])
        ->and(permisosMatriculaDe('Padre de Familia'))->toBe(['matricula.ser_apoderado', 'matricula.ver_propia'])
        ->and(permisosMatriculaDe('Alumno'))->toBe(['matricula.ser_alumno', 'matricula.ver_propia']);
});

it('es idempotente y no pisa los cambios del Administrador', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Rol::where('nombre', 'Director')->first()->permisos()->detach(Permiso::where('identificador', 'matricula.ver_todas')->value('id'));

    $this->seed(RolesYPermisosSeeder::class);

    expect(Permiso::where('identificador', 'like', 'matricula.%')->count())->toBe(6)
        ->and(permisosMatriculaDe('Director'))->toBeEmpty();
});
