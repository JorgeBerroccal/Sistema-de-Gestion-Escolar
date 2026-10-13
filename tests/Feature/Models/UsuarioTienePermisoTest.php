<?php

use App\Models\Permiso;
use Illuminate\Support\Facades\DB;

it('es verdadero para un permiso del rol y falso para uno ajeno o inexistente', function () {
    $usuario = usuarioConPermisos(['usuarios.ver']);
    Permiso::factory()->create(['identificador' => 'usuarios.crear']);

    expect($usuario->tienePermiso('usuarios.ver'))->toBeTrue()
        ->and($usuario->tienePermiso('usuarios.crear'))->toBeFalse()
        ->and($usuario->tienePermiso('no.existe'))->toBeFalse();
});

it('no ejecuta más consultas tras la primera llamada en la misma instancia', function () {
    $usuario = usuarioConPermisos(['usuarios.ver'])->fresh();

    $usuario->tienePermiso('usuarios.ver');
    DB::enableQueryLog();
    $usuario->tienePermiso('usuarios.ver');
    $usuario->tienePermiso('otro.permiso');

    expect(DB::getQueryLog())->toBeEmpty();
});

it('una instancia nueva refleja los cambios en rol_permiso', function () {
    $usuario = usuarioConPermisos(['usuarios.ver']);
    expect($usuario->tienePermiso('usuarios.ver'))->toBeTrue();

    $usuario->rol->permisos()->detach();

    expect($usuario->fresh()->tienePermiso('usuarios.ver'))->toBeFalse();
});

it('una cuenta inactiva no tiene permisos', function () {
    $usuario = usuarioConPermisos(['usuarios.ver'], ['activo' => false]);

    expect($usuario->tienePermiso('usuarios.ver'))->toBeFalse();
});
