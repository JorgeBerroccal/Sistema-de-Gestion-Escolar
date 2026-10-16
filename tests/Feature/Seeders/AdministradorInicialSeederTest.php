<?php

use App\Models\Usuario;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]);
});

it('crea el administrador activo con rol Administrador y puede iniciar sesión', function () {
    $this->seed(AdministradorInicialSeeder::class);

    $admin = Usuario::where('username', 'admin')->first();
    expect($admin->activo)->toBeTrue()
        ->and($admin->email)->toBe('admin@example.com')
        ->and($admin->rol->nombre)->toBe('Administrador')
        ->and(Gate::forUser($admin)->allows('roles.asignar_permisos'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('usuarios.editar'))->toBeTrue();

    $this->post('/login', ['login' => 'admin', 'password' => 'secreto123'])->assertRedirect('/inicio');
});

it('no cambia la contraseña de un usuario existente', function () {
    $this->seed(AdministradorInicialSeeder::class);
    config(['sge.admin.password' => 'otra-clave-99']);

    $this->seed(AdministradorInicialSeeder::class);

    $this->post('/login', ['login' => 'admin', 'password' => 'secreto123'])->assertRedirect('/inicio');
    expect(Usuario::count())->toBe(1);
});

it('falla si no hay contraseña', function () {
    config(['sge.admin.password' => '']);

    expect(fn () => $this->seed(AdministradorInicialSeeder::class))
        ->toThrow(RuntimeException::class, 'SGE_ADMIN_PASSWORD');
});

it('falla y no crea el usuario si la contraseña tiene menos de 8 caracteres', function () {
    config(['sge.admin.password' => 'corta']);

    expect(fn () => $this->seed(AdministradorInicialSeeder::class))->toThrow(RuntimeException::class);
    expect(Usuario::count())->toBe(0);
});

it('falla con mensaje claro si falta SGE_ADMIN_USERNAME', function (?string $valor) {
    config(['sge.admin.username' => $valor]);

    expect(fn () => (new AdministradorInicialSeeder)->run())->toThrow(RuntimeException::class, 'SGE_ADMIN_USERNAME');
    expect(Usuario::count())->toBe(0);
})->with([[null], [''], ['   ']]);

it('falla con mensaje claro si no existe el rol Administrador', function () {
    DB::table('rol_permiso')->delete();
    DB::table('roles')->delete();

    expect(fn () => (new AdministradorInicialSeeder)->run())->toThrow(RuntimeException::class, 'Administrador');
});
