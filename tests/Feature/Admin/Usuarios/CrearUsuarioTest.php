<?php

use App\Livewire\Admin\Usuarios\Form;
use App\Models\Rol;
use App\Models\Usuario;
use Livewire\Livewire;

function datosUsuarioValidos(array $extra = []): array
{
    return array_merge([
        'nombres' => 'Ana',
        'apellidos' => 'Pérez',
        'username' => 'ana.perez',
        'email' => 'Ana@Colegio.pe',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
        'rol_id' => Rol::factory()->create()->id,
    ], $extra);
}

function llenarForm($componente, array $datos)
{
    foreach ($datos as $k => $v) {
        $componente->set($k, $v);
    }

    return $componente;
}

it('muestra el formulario solo con usuarios.crear', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.crear']))->get('/admin/usuarios/crear')->assertOk();
    $this->actingAs(usuarioConPermisos([]))->get('/admin/usuarios/crear')->assertForbidden();
});

it('crea un usuario activo con username y email en minúsculas', function () {
    $actor = usuarioConPermisos(['usuarios.crear']);

    llenarForm(Livewire::actingAs($actor)->test(Form::class), datosUsuarioValidos(['username' => 'ana.perez']))
        ->call('guardar')->assertHasNoErrors()->assertRedirect(route('admin.usuarios.index'));

    $u = Usuario::where('username', 'ana.perez')->firstOrFail();
    expect($u->activo)->toBeTrue()->and($u->email)->toBe('ana@colegio.pe');
});

it('permite crear sin email', function () {
    $actor = usuarioConPermisos(['usuarios.crear']);

    llenarForm(Livewire::actingAs($actor)->test(Form::class), datosUsuarioValidos(['email' => '']))
        ->call('guardar')->assertHasNoErrors();

    expect(Usuario::where('username', 'ana.perez')->first()->email)->toBeNull();
});

it('valida los campos al crear', function (array $cambio, string $campo) {
    $actor = usuarioConPermisos(['usuarios.crear']);
    Usuario::factory()->create(['username' => 'existente', 'email' => 'existente@x.pe']);

    llenarForm(Livewire::actingAs($actor)->test(Form::class), datosUsuarioValidos($cambio))
        ->call('guardar')->assertHasErrors($campo);

    expect(Usuario::where('username', 'ana.perez')->exists())->toBeFalse();
})->with([
    'username duplicado sin distinguir mayúsculas' => [['username' => 'EXISTENTE'], 'username'],
    'email duplicado' => [['email' => 'Existente@x.pe'], 'email'],
    'username con arroba' => [['username' => 'ana@colegio'], 'username'],
    'username muy corto' => [['username' => 'an'], 'username'],
    'username con espacio' => [['username' => 'ana perez'], 'username'],
    'password corta' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'password sin confirmar' => [['password_confirmation' => 'otra'], 'password'],
    'rol inexistente' => [['rol_id' => 999999], 'rol_id'],
    'nombres vacío' => [['nombres' => ''], 'nombres'],
    'apellidos vacío' => [['apellidos' => ''], 'apellidos'],
    'nombres > 100' => [['nombres' => str_repeat('a', 101)], 'nombres'],
    'apellidos > 100' => [['apellidos' => str_repeat('a', 101)], 'apellidos'],
]);

it('rechaza guardar sin usuarios.crear y no crea nada', function () {
    $actor = usuarioConPermisos(['usuarios.crear']);
    $c = llenarForm(Livewire::actingAs($actor)->test(Form::class), datosUsuarioValidos());
    $antes = Usuario::count();

    // Se retira el permiso tras montar: la acción vuelve a autorizar.
    $actor->rol->permisos()->detach();
    auth()->setUser($actor->fresh());
    $c->call('guardar')->assertForbidden();

    expect(Usuario::count())->toBe($antes);
});

it('el usuario creado puede iniciar sesión', function () {
    $actor = usuarioConPermisos(['usuarios.crear']);

    llenarForm(Livewire::actingAs($actor)->test(Form::class), datosUsuarioValidos())->call('guardar');
    auth()->logout();

    $this->post('/login', ['login' => 'ana.perez', 'password' => 'secreto123'])->assertRedirect('/inicio');
    $this->assertAuthenticated();
});
