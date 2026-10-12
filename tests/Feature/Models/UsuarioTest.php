<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('guarda username y email en minúsculas', function () {
    $usuario = Usuario::factory()->create(['username' => 'JPerez', 'email' => 'JPerez@Example.COM']);

    expect($usuario->fresh())
        ->username->toBe('jperez')
        ->email->toBe('jperez@example.com');
});

it('guarda el email vacío como null', function () {
    $usuario = Usuario::factory()->create(['email' => '']);

    expect($usuario->fresh()->email)->toBeNull();
});

it('permite varios usuarios sin email', function () {
    Usuario::factory()->count(2)->create(['email' => null]);

    expect(Usuario::whereNull('email')->count())->toBe(2);
});

it('guarda la contraseña con hash', function () {
    $usuario = Usuario::factory()->create(['password' => 'secreto123']);

    expect($usuario->password)->not->toBe('secreto123')
        ->and(Hash::check('secreto123', $usuario->password))->toBeTrue();
});

it('no permite insertar un usuario sin rol_id', function () {
    DB::table('usuarios')->insert([
        'nombres' => 'A', 'apellidos' => 'B', 'username' => 'sinrol',
        'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('no permite un rol_id inexistente', function () {
    Usuario::factory()->create(['rol_id' => 999999]);
})->throws(QueryException::class);

it('compone el nombre completo y expone su rol', function () {
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe', 'rol_id' => $rol->id]);

    expect($usuario->nombre_completo)->toBe('Ana Quispe')
        ->and($usuario->rol->is($rol))->toBeTrue();
});
