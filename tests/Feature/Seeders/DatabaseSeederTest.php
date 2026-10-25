<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]));

it('siembra roles, permisos y el administrador inicial sin usuarios demo en testing', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Rol::count())->toBe(5)
        ->and(Permiso::count())->toBe(13)
        ->and(Usuario::pluck('username')->all())->toBe(['admin']);
});

it('crea un usuario demo por rol (y dos docentes más del Módulo 2) en local y es idempotente', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Usuario::count())->toBe(7)
        ->and(Usuario::with('rol')->get()->pluck('rol.nombre')->sort()->values()->all())
        ->toBe(['Administrador', 'Alumno', 'Director', 'Docente', 'Docente', 'Docente', 'Padre de Familia']);
});
