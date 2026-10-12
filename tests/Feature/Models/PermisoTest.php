<?php

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\QueryException;

it('crea un permiso', function () {
    $permiso = Permiso::create(['identificador' => 'usuarios.ver', 'nombre' => 'Ver usuarios']);

    expect($permiso->exists)->toBeTrue();
});

it('permite cambiar nombre y descripción', function () {
    $permiso = Permiso::factory()->create();

    $permiso->update(['nombre' => 'Nuevo nombre', 'descripcion' => 'Nueva descripción']);

    expect($permiso->fresh())->nombre->toBe('Nuevo nombre')->descripcion->toBe('Nueva descripción');
});

it('lanza LogicException al cambiar el identificador', function () {
    $permiso = Permiso::factory()->create();

    $permiso->update(['identificador' => 'otro.permiso']);
})->throws(LogicException::class, 'El identificador de un permiso es inmutable.');

it('valida el formato del identificador con la constante', function () {
    expect(preg_match(Permiso::REGEX_IDENTIFICADOR, 'usuarios.crear'))->toBe(1)
        ->and(preg_match(Permiso::REGEX_IDENTIFICADOR, 'Usuarios'))->toBe(0)
        ->and(preg_match(Permiso::REGEX_IDENTIFICADOR, 'usuarios.'))->toBe(0);
});

it('rechaza un par rol–permiso duplicado', function () {
    $rol = Rol::factory()->create();
    $permiso = Permiso::factory()->create();

    $rol->permisos()->attach($permiso);
    $rol->permisos()->attach($permiso);
})->throws(QueryException::class);
