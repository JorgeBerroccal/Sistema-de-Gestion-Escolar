<?php

use App\Livewire\Admin\Usuarios\Index;
use App\Models\Usuario;
use Livewire\Livewire;

it('desactiva y reactiva un usuario', function () {
    $actor = usuarioConPermisos(['usuarios.ver', 'usuarios.desactivar']);
    $objetivo = Usuario::factory()->create(['username' => 'objetivo', 'password' => 'secreto123']);

    Livewire::actingAs($actor)->test(Index::class)->call('desactivar', $objetivo->id)->assertHasNoErrors();
    expect($objetivo->fresh()->activo)->toBeFalse();

    auth()->logout();
    $this->post('/login', ['login' => 'objetivo', 'password' => 'secreto123'])
        ->assertSessionHasErrors(['login' => 'Tu cuenta está inactiva. Contacta al administrador.']);

    Livewire::actingAs($actor)->test(Index::class)->call('reactivar', $objetivo->id);
    expect($objetivo->fresh()->activo)->toBeTrue();
});

it('no permite desactivar la propia cuenta', function () {
    $actor = usuarioConPermisos(['usuarios.ver', 'usuarios.desactivar']);

    Livewire::actingAs($actor)->test(Index::class)->call('desactivar', $actor->id)
        ->assertHasErrors('general')->assertSee('No puedes desactivar tu propia cuenta');

    expect($actor->fresh()->activo)->toBeTrue();
});

it('no permite desactivar la única cuenta administradora', function () {
    $admin = usuarioConPermisos(['usuarios.editar', 'roles.asignar_permisos']);
    $actor = usuarioConPermisos(['usuarios.ver', 'usuarios.desactivar']);

    Livewire::actingAs($actor)->test(Index::class)->call('desactivar', $admin->id)->assertHasErrors('general');

    expect($admin->fresh()->activo)->toBeTrue();
});

it('permite desactivar un administrador si hay otro', function () {
    $admin = usuarioConPermisos(['usuarios.editar', 'roles.asignar_permisos']);
    Usuario::factory()->create(['rol_id' => $admin->rol_id]);
    $actor = usuarioConPermisos(['usuarios.ver', 'usuarios.desactivar']);

    Livewire::actingAs($actor)->test(Index::class)->call('desactivar', $admin->id)->assertHasNoErrors();

    expect($admin->fresh()->activo)->toBeFalse();
});

it('deniega desactivar y reactivar sin usuarios.desactivar', function () {
    $actor = usuarioConPermisos(['usuarios.ver']);
    $activo = Usuario::factory()->create();
    $inactivo = Usuario::factory()->inactivo()->create();

    Livewire::actingAs($actor)->test(Index::class)->call('desactivar', $activo->id)->assertForbidden();
    Livewire::actingAs($actor)->test(Index::class)->call('reactivar', $inactivo->id)->assertForbidden();

    expect($activo->fresh()->activo)->toBeTrue()->and($inactivo->fresh()->activo)->toBeFalse();
});
