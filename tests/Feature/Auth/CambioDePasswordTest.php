<?php

use App\Livewire\Admin\Usuarios\Form;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

function usuarioConPassword(array $extra = []): Usuario
{
    return Usuario::factory()->for(Rol::factory())->create(['password' => 'clave-vieja-1'] + $extra);
}

it('rechaza la contraseña actual incorrecta', function () {
    $u = usuarioConPassword();

    $this->actingAs($u)->put(route('cuenta.password.actualizar'), [
        'password_actual' => 'incorrecta', 'password' => 'clave-nueva-1', 'password_confirmation' => 'clave-nueva-1',
    ])->assertSessionHasErrors('password_actual');

    expect(Hash::check('clave-vieja-1', $u->fresh()->password))->toBeTrue();
});

it('aplica la longitud mínima y la confirmación', function () {
    $u = usuarioConPassword();

    $this->actingAs($u)->put(route('cuenta.password.actualizar'), [
        'password_actual' => 'clave-vieja-1', 'password' => 'corta', 'password_confirmation' => 'corta',
    ])->assertSessionHasErrors('password');

    $this->actingAs($u)->put(route('cuenta.password.actualizar'), [
        'password_actual' => 'clave-vieja-1', 'password' => 'clave-nueva-1', 'password_confirmation' => 'otra-distinta',
    ])->assertSessionHasErrors('password');
});

it('cambia la contraseña y limpia el cambio pendiente', function () {
    $u = usuarioConPassword(['debe_cambiar_password' => true]);

    $this->actingAs($u)->put(route('cuenta.password.actualizar'), [
        'password_actual' => 'clave-vieja-1', 'password' => 'clave-nueva-1', 'password_confirmation' => 'clave-nueva-1',
    ])->assertRedirect(route('inicio'));

    $u->refresh();
    expect(Hash::check('clave-nueva-1', $u->password))->toBeTrue()->and($u->debe_cambiar_password)->toBeFalse();
});

it('con un cambio pendiente redirige a Mi cuenta desde cualquier otra ruta', function () {
    $u = usuarioConPassword(['debe_cambiar_password' => true]);

    $this->actingAs($u)->get(route('inicio'))->assertRedirect(route('cuenta.password'));
    $this->actingAs($u)->get(route('asistente.index'))->assertRedirect(route('cuenta.password'));
    $this->actingAs($u)->get(route('cuenta.password'))->assertOk()->assertSee('Debes definir una contraseña nueva');
});

it('con un cambio pendiente sí permite cerrar la sesión', function () {
    $u = usuarioConPassword(['debe_cambiar_password' => true]);

    $this->actingAs($u)->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('sin cambio pendiente el usuario navega con normalidad y ve Mi cuenta', function () {
    $u = usuarioConPassword();

    $this->actingAs($u)->get(route('inicio'))->assertOk()->assertSee('Mi cuenta');
});

it('el Administrador crea y restablece cuentas con cambio obligatorio', function () {
    $u = usuarioConPassword(['debe_cambiar_password' => false]);

    Livewire\Livewire::actingAs(Usuario::factory()->for(Rol::factory())->create())->test(Form::class, ['usuario' => $u])
        ->assertForbidden();

    $admin = Usuario::factory()->create(['rol_id' => Rol::factory()->create()->id]);
    $permisos = ['usuarios.crear', 'usuarios.editar'];
    foreach ($permisos as $id) {
        $admin->rol->permisos()->attach(Permiso::firstOrCreate(['identificador' => $id], ['nombre' => $id, 'modulo' => 'admin'])->id);
    }

    Livewire\Livewire::actingAs($admin)->test(Form::class, ['usuario' => $u])
        ->set('password', 'restablecida-1')->set('password_confirmation', 'restablecida-1')
        ->call('restablecerPassword')->assertHasNoErrors();
    expect($u->fresh()->debe_cambiar_password)->toBeTrue();

    Livewire\Livewire::actingAs($admin)->test(Form::class)
        ->set('nombres', 'Ana')->set('apellidos', 'Paz')->set('username', 'ana.paz')
        ->set('rol_id', $admin->rol_id)->set('password', 'inicial-123')->set('password_confirmation', 'inicial-123')
        ->call('guardar')->assertHasNoErrors();
    expect(Usuario::where('username', 'ana.paz')->value('debe_cambiar_password'))->toBeTrue();
});

it('al cambiar la contraseña invalida las demás sesiones (el hash de sesión pasa a ser el nuevo)', function () {
    $u = usuarioConPassword();

    $this->actingAs($u)->put(route('cuenta.password.actualizar'), [
        'password_actual' => 'clave-vieja-1', 'password' => 'clave-nueva-1', 'password_confirmation' => 'clave-nueva-1',
    ]);

    expect(session('password_hash_web'))->toBe(auth()->guard('web')->hashPasswordForCookie($u->fresh()->getAuthPassword()));
});
