<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Admin\Usuarios\Index;
use App\Models\Usuario;
use Livewire\Livewire;

it('una cuenta desactivada a mitad de sesión no puede ejecutar acciones Livewire', function () {
    $u = usuarioConPermisos(['usuarios.ver', 'usuarios.desactivar']);
    $otro = Usuario::factory()->create();

    $c = Livewire::actingAs($u)->test(Index::class);
    $u->update(['activo' => false]);

    $c->call('desactivar', $otro->id)->assertForbidden();

    expect($otro->fresh()->activo)->toBeTrue();
});

it('registra EnsureUserIsActive como middleware persistente de Livewire', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserIsActive::class);
});
