<?php

use App\Models\Usuario;

it('cierra la sesión con POST /logout', function () {
    $this->actingAs(Usuario::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
    $this->get('/inicio')->assertRedirect('/login');
});

it('redirige a /login al visitante sin sesión', function () {
    $this->get('/inicio')->assertRedirect('/login');
});

it('redirige / a /inicio cuando hay sesión', function () {
    $this->actingAs(Usuario::factory()->create())->get('/')->assertRedirect('/inicio');
});

it('muestra /inicio a un usuario sin ningún permiso', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/inicio')
        ->assertOk()
        ->assertSee('Bienvenido');
});

it('exige autenticación para cerrar sesión', function () {
    $this->post('/logout')->assertRedirect('/login');
});
