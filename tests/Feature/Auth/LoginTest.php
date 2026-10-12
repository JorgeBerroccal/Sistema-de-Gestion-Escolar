<?php

use App\Models\Usuario;

it('muestra el formulario de login', function () {
    $this->get('/login')->assertOk()->assertSee('Correo o nombre de usuario');
});

it('inicia sesión con correo', function () {
    $usuario = Usuario::factory()->create(['email' => 'ana@colegio.pe']);

    $this->post('/login', ['login' => 'ana@colegio.pe', 'password' => 'password'])
        ->assertRedirect('/inicio');

    $this->assertAuthenticatedAs($usuario);
});

it('inicia sesión con nombre de usuario', function () {
    $usuario = Usuario::factory()->create(['username' => 'ana.perez']);

    $this->post('/login', ['login' => 'ana.perez', 'password' => 'password'])
        ->assertRedirect('/inicio');

    $this->assertAuthenticatedAs($usuario);
});

it('no distingue mayúsculas en el login', function () {
    $usuario = Usuario::factory()->create(['username' => 'ana.perez', 'email' => 'ana@colegio.pe']);

    $this->post('/login', ['login' => 'ANA.PEREZ', 'password' => 'password'])->assertRedirect('/inicio');
    $this->assertAuthenticatedAs($usuario);

    auth()->logout();

    $this->post('/login', ['login' => 'Ana@Colegio.PE', 'password' => 'password'])->assertRedirect('/inicio');
    $this->assertAuthenticatedAs($usuario);
});

it('rechaza una contraseña incorrecta', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);

    $this->from('/login')->post('/login', ['login' => 'ana.perez', 'password' => 'incorrecta'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Las credenciales no son válidas.']);

    $this->assertGuest();
});

it('responde igual para un usuario inexistente', function () {
    $this->from('/login')->post('/login', ['login' => 'nadie', 'password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Las credenciales no son válidas.']);

    $this->assertGuest();
});

it('valida los campos obligatorios', function () {
    $this->from('/login')->post('/login', [])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login', 'password']);
});

it('regenera la sesión tras un login exitoso', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);

    $this->get('/login');
    $antes = session()->getId();

    $this->post('/login', ['login' => 'ana.perez', 'password' => 'password']);

    expect(session()->getId())->not->toBe($antes);
});

it('redirige a /inicio al usuario autenticado que visita /login', function () {
    $this->actingAs(Usuario::factory()->create())->get('/login')->assertRedirect('/inicio');
});
