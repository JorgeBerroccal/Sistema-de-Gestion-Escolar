<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LoadTestSeeder;

it('crea 1000 usuarios activos con el rol Carga que solo tiene usuarios.ver', function () {
    $this->seed(LoadTestSeeder::class);

    $rol = Rol::where('nombre', 'Carga')->firstOrFail();

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(1000)
        ->and(Usuario::where('username', 'carga0001')->value('activo'))->toBeTrue()
        ->and(Usuario::where('username', 'carga1000')->exists())->toBeTrue()
        ->and(Usuario::where('username', 'like', 'carga%')->where('rol_id', '!=', $rol->id)->count())->toBe(0)
        ->and(Usuario::where('username', 'like', 'carga%')->where('activo', false)->count())->toBe(0)
        ->and($rol->permisos()->pluck('identificador')->all())->toBe(['usuarios.ver']);
});

it('no duplica al ejecutarse otra vez', function () {
    $this->seed(LoadTestSeeder::class);
    $this->seed(LoadTestSeeder::class);

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(1000)
        ->and(Rol::where('nombre', 'Carga')->count())->toBe(1);
});

it('permite iniciar sesión con la contraseña password', function () {
    $this->seed(LoadTestSeeder::class);

    $this->post('/login', ['login' => 'carga0007', 'password' => 'password'])->assertRedirect('/inicio');
});

it('lanza excepción en producción y no crea usuarios', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new LoadTestSeeder)->run())->toThrow(RuntimeException::class);
    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
});

it('DatabaseSeeder no crea usuarios carga*', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
});
