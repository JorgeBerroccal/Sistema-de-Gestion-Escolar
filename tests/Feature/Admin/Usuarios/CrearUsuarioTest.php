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

// T103 (SC-007): mejoras de usabilidad del formulario de creación

it('sugiere el username a partir del primer nombre y el primer apellido', function () {
    Livewire::actingAs(usuarioConPermisos(['usuarios.crear']))->test(Form::class)
        ->set('nombres', 'Rosa María')->set('apellidos', 'Quispe Huamán')
        ->assertSet('username', 'rosa.quispe');
});

it('sigue actualizando la sugerencia mientras el username no se edite a mano', function () {
    Livewire::actingAs(usuarioConPermisos(['usuarios.crear']))->test(Form::class)
        ->set('nombres', 'Rosa')->set('apellidos', 'Quispe')->assertSet('username', 'rosa.quispe')
        ->set('apellidos', 'Mamani')->assertSet('username', 'rosa.mamani');
});

it('no pisa un username editado a mano y lo guarda tal cual', function () {
    $actor = usuarioConPermisos(['usuarios.crear']);

    Livewire::actingAs($actor)->test(Form::class)
        ->set('nombres', 'Rosa')->set('apellidos', 'Quispe')
        ->set('username', 'rquispe')
        ->set('nombres', 'Rosa María')->set('apellidos', 'Quispe Huamán')
        ->assertSet('username', 'rquispe')
        ->set('email', '')->set('password', 'secreto123')->set('password_confirmation', 'secreto123')
        ->set('rol_id', Rol::factory()->create()->id)
        ->call('guardar')->assertHasNoErrors();

    expect(Usuario::where('username', 'rquispe')->exists())->toBeTrue();
});

it('retoma la sugerencia si se borra el username a mano', function () {
    Livewire::actingAs(usuarioConPermisos(['usuarios.crear']))->test(Form::class)
        ->set('username', 'manual')->set('nombres', 'Rosa')->set('apellidos', 'Quispe')
        ->assertSet('username', 'manual')
        ->set('username', '')->set('nombres', 'Rosita')
        ->assertSet('username', 'rosita.quispe');
});

it('mantiene el error de duplicado al guardar y no numera automáticamente', function () {
    Usuario::factory()->create(['username' => 'rosa.quispe']);

    Livewire::actingAs(usuarioConPermisos(['usuarios.crear']))->test(Form::class)
        ->set('nombres', 'Rosa María')->set('apellidos', 'Quispe Huamán')
        ->set('email', '')->set('password', 'secreto123')->set('password_confirmation', 'secreto123')
        ->set('rol_id', Rol::factory()->create()->id)
        ->assertSet('username', 'rosa.quispe')
        ->call('guardar')->assertHasErrors(['username' => 'unique'])
        ->assertSet('username', 'rosa.quispe');

    expect(Usuario::where('username', 'like', 'rosa.quispe%')->count())->toBe(1);
});

it('no sugiere username al editar un usuario existente', function () {
    $existente = Usuario::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Gómez', 'username' => 'lgomez']);

    Livewire::actingAs(usuarioConPermisos(['usuarios.editar']))->test(Form::class, ['usuario' => $existente])
        ->set('nombres', 'Luis Alberto')->assertSet('username', 'lgomez');
});

it('pone autofocus en Nombres solo al crear', function () {
    $crear = $this->actingAs(usuarioConPermisos(['usuarios.crear']))->get('/admin/usuarios/crear')->getContent();
    expect($crear)->toMatch('/<input[^>]*id="nombres"[^>]*\bautofocus\b/s');

    $existente = Usuario::factory()->create();
    $editar = $this->actingAs(usuarioConPermisos(['usuarios.editar']))->get("/admin/usuarios/{$existente->id}/editar")->getContent();
    expect($editar)->not->toContain('autofocus');
});

it('ordena el formulario con el correo opcional después del rol', function () {
    $html = $this->actingAs(usuarioConPermisos(['usuarios.crear']))->get('/admin/usuarios/crear')->getContent();
    $posicion = fn (string $id) => strpos($html, 'id="'.$id.'"');

    $orden = ['nombres', 'apellidos', 'username', 'password', 'password_confirmation', 'rol_id', 'email'];
    foreach ($orden as $id) {
        expect($posicion($id))->not->toBeFalse("falta el campo {$id}");
    }
    $posiciones = array_map($posicion, $orden);
    $ordenadas = $posiciones;
    sort($ordenadas);

    expect($posiciones)->toBe($ordenadas);
});

it('no usa wire:model.live en el formulario (mejora 4: nada que cambiar)', function () {
    expect(file_get_contents(resource_path('views/livewire/admin/usuarios/form.blade.php')))->not->toContain('.live');
});
