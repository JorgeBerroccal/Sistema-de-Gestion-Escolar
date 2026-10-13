<?php

use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;

function identificadoresDe(string $rol): array
{
    return Rol::where('nombre', $rol)->first()->permisos()->pluck('identificador')->sort()->values()->all();
}

it('crea los 5 roles, los 7 permisos y sus asignaciones', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(7)
        ->and(identificadoresDe('Administrador'))->toHaveCount(7)
        ->and(identificadoresDe('Director'))->toBe(['roles.ver', 'usuarios.ver'])
        ->and(identificadoresDe('Docente'))->toBeEmpty()
        ->and(identificadoresDe('Padre de Familia'))->toBeEmpty()
        ->and(identificadoresDe('Alumno'))->toBeEmpty();
});

it('es idempotente', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(RolesYPermisosSeeder::class);

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(7)
        ->and(identificadoresDe('Administrador'))->toHaveCount(7);
});

it('no vuelve a asignar un permiso que se quitó a un rol', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Rol::where('nombre', 'Director')->first()->permisos()->detach(Permiso::where('identificador', 'usuarios.ver')->value('id'));

    $this->seed(RolesYPermisosSeeder::class);

    expect(identificadoresDe('Director'))->toBe(['roles.ver']);
});
