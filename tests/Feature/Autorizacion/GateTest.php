<?php

use App\Models\Rol;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'activo', 'can:usuarios.ver'])->get('/_prueba-permiso', fn () => 'ok');
});

it('permite con el permiso y responde 403 sin él', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.ver']))->get('/_prueba-permiso')->assertOk();
    $this->actingAs(usuarioConPermisos([]))->get('/_prueba-permiso')->assertForbidden();
});

it('deniega habilidades inexistentes', function () {
    expect(Gate::forUser(usuarioConPermisos(['usuarios.ver']))->allows('permiso.inexistente'))->toBeFalse();
});

it('quitar u otorgar el permiso surte efecto en la siguiente solicitud', function () {
    $usuario = usuarioConPermisos(['usuarios.ver']);
    $this->actingAs($usuario)->get('/_prueba-permiso')->assertOk();

    $permiso = $usuario->rol->permisos()->first();
    $usuario->rol->permisos()->detach($permiso->id);
    // Cada solicitud real carga al usuario de nuevo (la memoización es solo por instancia).
    $this->actingAs($usuario->fresh())->get('/_prueba-permiso')->assertForbidden();

    $usuario->rol->permisos()->attach($permiso->id);
    $this->actingAs($usuario->fresh())->get('/_prueba-permiso')->assertOk();
});

it('cambiar el rol a uno sin el permiso surte efecto en la siguiente solicitud', function () {
    $usuario = usuarioConPermisos(['usuarios.ver']);
    $this->actingAs($usuario)->get('/_prueba-permiso')->assertOk();

    $usuario->update(['rol_id' => Rol::factory()->create()->id]);

    $this->actingAs($usuario->fresh())->get('/_prueba-permiso')->assertForbidden();
});
