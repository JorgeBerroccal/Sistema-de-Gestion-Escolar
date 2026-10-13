<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Usuario;

function intentoFallido(string $login = 'ana.perez')
{
    return test()->from('/login')->post('/login', ['login' => $login, 'password' => 'mala']);
}

it('bloquea el 6.º intento aunque la contraseña sea correcta', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);

    foreach (range(1, 5) as $i) {
        intentoFallido()->assertSessionHasErrors(['login' => 'Las credenciales no son válidas.']);
    }

    $respuesta = $this->from('/login')->post('/login', ['login' => 'ana.perez', 'password' => 'password']);

    $respuesta->assertRedirect('/login')->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toStartWith('Demasiados intentos.');
    $this->assertGuest();
});

it('permite volver a intentar pasados 60 segundos', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);
    foreach (range(1, 5) as $i) {
        intentoFallido();
    }

    $this->travel(61)->seconds();

    $this->post('/login', ['login' => 'ana.perez', 'password' => 'password'])->assertRedirect('/inicio');
});

it('limpia el contador con un login exitoso', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);
    foreach (range(1, 4) as $i) {
        intentoFallido();
    }

    $this->post('/login', ['login' => 'ana.perez', 'password' => 'password'])->assertRedirect('/inicio');
    auth()->logout();

    foreach (range(1, 5) as $i) {
        intentoFallido()->assertSessionHasErrors(['login' => 'Las credenciales no son válidas.']);
    }
});

it('no afecta a los intentos con otro login', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);
    Usuario::factory()->create(['username' => 'luis.diaz']);
    foreach (range(1, 6) as $i) {
        intentoFallido('ana.perez');
    }

    $this->post('/login', ['login' => 'luis.diaz', 'password' => 'password'])->assertRedirect('/inicio');
});

it('cuenta como fallo el login con cuenta inactiva y contraseña correcta, y bloquea el 6.º', function () {
    Usuario::factory()->inactivo()->create(['username' => 'ana.perez']);
    $this->freezeTime();

    foreach (range(1, 5) as $i) {
        $this->from('/login')->post('/login', ['login' => 'ana.perez', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => LoginRequest::MENSAJE_INACTIVA]);
    }

    $this->from('/login')->post('/login', ['login' => 'ana.perez', 'password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Demasiados intentos. Intenta nuevamente en 60 segundos.']);
    $this->assertGuest();
});

it('no cuentan como fallo los logins exitosos', function () {
    Usuario::factory()->create(['username' => 'ana.perez']);

    foreach (range(1, 8) as $i) {
        $this->post('/login', ['login' => 'ana.perez', 'password' => 'password'])->assertRedirect('/inicio');
        auth()->logout();
    }
});
