<?php

use App\Livewire\Admin\Usuarios\Form;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Livewire\Livewire;

it('muestra la edición solo con usuarios.editar', function () {
    $objetivo = Usuario::factory()->create();

    $this->actingAs(usuarioConPermisos(['usuarios.editar']))->get("/admin/usuarios/{$objetivo->id}/editar")->assertOk();
    $this->actingAs(usuarioConPermisos(['usuarios.crear']))->get("/admin/usuarios/{$objetivo->id}/editar")->assertForbidden();
});

it('edita datos respetando la unicidad e ignorando al propio usuario', function () {
    $actor = usuarioConPermisos(['usuarios.editar']);
    $objetivo = Usuario::factory()->create(['username' => 'objetivo', 'email' => 'obj@x.pe']);
    Usuario::factory()->create(['username' => 'otro', 'email' => 'otro@x.pe']);

    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('nombres', 'Nuevo')->call('guardar')->assertHasNoErrors();
    expect($objetivo->fresh()->nombres)->toBe('Nuevo');

    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('username', 'otro')->set('email', 'otro@x.pe')
        ->call('guardar')->assertHasErrors(['username', 'email']);
});

it('cambiar el rol aplica los permisos del nuevo rol en la siguiente solicitud', function () {
    $actor = usuarioConPermisos(['usuarios.editar']);
    $objetivo = usuarioConPermisos([]);
    $rolNuevo = Rol::factory()->create();
    $rolNuevo->permisos()->sync([Permiso::firstOrCreate(['identificador' => 'usuarios.ver'], ['nombre' => 'ver'])->id]);

    $this->actingAs($objetivo)->get('/admin/usuarios')->assertForbidden();

    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('rol_id', $rolNuevo->id)->call('guardar')->assertHasNoErrors();

    $this->actingAs($objetivo->fresh())->get('/admin/usuarios')->assertOk();
});

it('no cambia el rol de la única cuenta administradora a un rol sin administración', function () {
    $admin = usuarioConPermisos(['usuarios.editar', 'roles.asignar_permisos']);
    $rolSinNada = Rol::factory()->create();

    Livewire::actingAs($admin)->test(Form::class, ['usuario' => $admin])
        ->set('rol_id', $rolSinNada->id)->call('guardar')->assertHasErrors('rol_id');

    expect($admin->fresh()->rol_id)->not->toBe($rolSinNada->id);
});

it('restablece la contraseña', function () {
    $actor = usuarioConPermisos(['usuarios.editar']);
    $objetivo = Usuario::factory()->create(['username' => 'objetivo', 'password' => 'vieja12345']);

    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('password', 'nueva12345')->set('password_confirmation', 'nueva12345')
        ->call('restablecerPassword')->assertHasNoErrors();

    auth()->logout();
    $this->post('/login', ['login' => 'objetivo', 'password' => 'vieja12345'])->assertSessionHasErrors('login');
    $this->post('/login', ['login' => 'objetivo', 'password' => 'nueva12345'])->assertRedirect('/inicio');
});

it('valida la contraseña al restablecer', function () {
    $actor = usuarioConPermisos(['usuarios.editar']);
    $objetivo = Usuario::factory()->create();

    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('password', 'corta')->set('password_confirmation', 'corta')->call('restablecerPassword')->assertHasErrors('password');
    Livewire::actingAs($actor)->test(Form::class, ['usuario' => $objetivo])
        ->set('password', 'suficiente1')->set('password_confirmation', 'distinta11')->call('restablecerPassword')->assertHasErrors('password');
});

it('deniega guardar y restablecer sin usuarios.editar', function () {
    $objetivo = Usuario::factory()->create(['nombres' => 'Original']);
    $hash = $objetivo->password;

    // Se monta con permiso y se retira antes de la acción (las acciones vuelven a autorizar).
    $editor = usuarioConPermisos(['usuarios.editar']);
    $guardar = Livewire::actingAs($editor)->test(Form::class, ['usuario' => $objetivo]);
    $restablecer = Livewire::actingAs($editor)->test(Form::class, ['usuario' => $objetivo]);
    $editor->rol->permisos()->detach();
    auth()->setUser($editor->fresh());

    $guardar->set('nombres', 'Hackeado')->call('guardar')->assertForbidden();
    $restablecer->set('password', 'nueva12345')->set('password_confirmation', 'nueva12345')
        ->call('restablecerPassword')->assertForbidden();

    expect($objetivo->fresh()->nombres)->toBe('Original')->and($objetivo->fresh()->password)->toBe($hash);
});
