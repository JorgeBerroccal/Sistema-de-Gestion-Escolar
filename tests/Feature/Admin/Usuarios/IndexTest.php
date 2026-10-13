<?php

use App\Livewire\Admin\Usuarios\Index;
use App\Models\Usuario;
use Livewire\Livewire;

it('lista usuarios con nombre, username, email, rol y estado', function () {
    $admin = usuarioConPermisos(['usuarios.ver']);
    $otro = Usuario::factory()->inactivo()->create(['nombres' => 'Marta', 'apellidos' => 'Quispe', 'username' => 'mquispe']);

    $this->actingAs($admin)->get('/admin/usuarios')
        ->assertOk()
        ->assertSee('Marta Quispe')
        ->assertSee('mquispe')
        ->assertSee($otro->email)
        ->assertSee($otro->rol->nombre)
        ->assertSee('Inactivo');
});

it('deniega la lista sin usuarios.ver', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/admin/usuarios')->assertForbidden();
});

it('filtra por nombres, apellidos, username o email', function () {
    $admin = usuarioConPermisos(['usuarios.ver'], ['nombres' => 'Zzz', 'apellidos' => 'Yyy', 'username' => 'zadmin', 'email' => 'zadmin@x.pe']);
    Usuario::factory()->create(['nombres' => 'Lucia', 'apellidos' => 'Rojas', 'username' => 'lrojas', 'email' => 'lu@x.pe']);
    Usuario::factory()->create(['nombres' => 'Pedro', 'apellidos' => 'Salas', 'username' => 'psalas', 'email' => 'pe@x.pe']);

    foreach (['Lucia', 'Rojas', 'lrojas', 'lu@x'] as $texto) {
        Livewire::actingAs($admin)->test(Index::class)->set('q', $texto)
            ->assertSee('lrojas')->assertDontSee('psalas');
    }
});

it('pagina de 15 en 15', function () {
    $admin = usuarioConPermisos(['usuarios.ver']);
    Usuario::factory()->count(20)->create();

    $c = Livewire::actingAs($admin)->test(Index::class);

    expect($c->viewData('usuarios')->perPage())->toBe(15)
        ->and($c->viewData('usuarios')->total())->toBe(21);
});
