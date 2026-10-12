<?php

use App\Models\Usuario;

const MENSAJE_INACTIVA = 'Tu cuenta está inactiva. Contacta al administrador.';

it('rechaza el login de una cuenta inactiva', function () {
    Usuario::factory()->inactivo()->create(['username' => 'ana.perez']);

    $this->from('/login')->post('/login', ['login' => 'ana.perez', 'password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => MENSAJE_INACTIVA]);

    $this->assertGuest();
});

it('desloguea en la siguiente solicitud a un usuario desactivado', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario)->get('/inicio')->assertOk();

    $usuario->update(['activo' => false]);

    $this->get('/inicio')
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => MENSAJE_INACTIVA]);

    $this->assertGuest();
});

it('no permite volver a entrar con la cuenta desactivada', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario)->get('/inicio');
    $usuario->update(['activo' => false]);
    $this->get('/inicio');

    $this->get('/inicio')->assertRedirect('/login');
});
