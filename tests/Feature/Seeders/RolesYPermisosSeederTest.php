<?php

use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;

function identificadoresDe(string $rol): array
{
    return Rol::where('nombre', $rol)->first()->permisos()->pluck('identificador')->sort()->values()->all();
}

it('crea los 5 roles, los 13 permisos y sus asignaciones', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(13)
        ->and(identificadoresDe('Administrador'))->toHaveCount(11)
        ->and(identificadoresDe('Administrador'))->not->toContain('docencia.dictar', 'asignaciones.ver_propias')
        ->and(identificadoresDe('Director'))->toBe(['academico.ver', 'asignaciones.ver_todas', 'roles.ver', 'usuarios.ver'])
        ->and(identificadoresDe('Docente'))->toBe(['asignaciones.ver_propias', 'docencia.dictar'])
        ->and(identificadoresDe('Padre de Familia'))->toBeEmpty()
        ->and(identificadoresDe('Alumno'))->toBeEmpty();
});

it('es idempotente', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(RolesYPermisosSeeder::class);

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(13)
        ->and(identificadoresDe('Administrador'))->toHaveCount(11);
});

it('no vuelve a asignar un permiso que se quitó a un rol', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Rol::where('nombre', 'Director')->first()->permisos()->detach(Permiso::where('identificador', 'usuarios.ver')->value('id'));

    $this->seed(RolesYPermisosSeeder::class);

    expect(identificadoresDe('Director'))->not->toContain('usuarios.ver');
});

it('no revierte permisos del Módulo 2 que el Administrador quitó o dio a otro rol', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $docencia = Permiso::where('identificador', 'docencia.dictar')->first();
    $docencia->roles()->detach();
    Rol::where('nombre', 'Director')->first()->permisos()->attach($docencia->id);

    $this->seed(RolesYPermisosSeeder::class);

    expect(identificadoresDe('Docente'))->not->toContain('docencia.dictar')
        ->and(identificadoresDe('Director'))->toContain('docencia.dictar');
});

it('no da ningún permiso del Módulo 2 a Padre de Familia ni a Alumno', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(identificadoresDe('Padre de Familia'))->toBeEmpty()
        ->and(identificadoresDe('Alumno'))->toBeEmpty();
});
