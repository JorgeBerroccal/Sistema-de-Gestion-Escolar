<?php

use App\Livewire\Admin\Roles\Permisos;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Livewire\Livewire;

function permisoId(string $identificador): int
{
    return Permiso::firstOrCreate(['identificador' => $identificador], ['nombre' => $identificador])->id;
}

it('muestra las casillas marcadas según rol_permiso', function () {
    $rol = Rol::factory()->create();
    $marcado = Permiso::factory()->create(['identificador' => 'marcado.si']);
    Permiso::factory()->create(['identificador' => 'marcado.no']);
    $rol->permisos()->sync([$marcado->id]);

    $c = Livewire::actingAs(usuarioConPermisos(['roles.ver']))->test(Permisos::class, ['rol' => $rol]);

    expect($c->get('seleccionados'))->toEqual([$marcado->id]);
    $this->actingAs(usuarioConPermisos(['roles.ver']))->get("/admin/roles/{$rol->id}/permisos")
        ->assertOk()->assertSee('marcado.si')->assertSee('marcado.no');
});

it('deniega la pantalla sin roles.ver', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/admin/roles/'.Rol::factory()->create()->id.'/permisos')->assertForbidden();
});

it('sincroniza rol_permiso y aplica en la siguiente solicitud sin reiniciar sesión', function () {
    $actor = usuarioConPermisos(['roles.ver', 'roles.asignar_permisos']);
    $rolObjetivo = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rolObjetivo->id]);
    $ver = permisoId('usuarios.ver');

    $this->actingAs($usuario)->get('/admin/usuarios')->assertForbidden();

    Livewire::actingAs($actor)->test(Permisos::class, ['rol' => $rolObjetivo])
        ->set('seleccionados', [$ver])->call('guardar')->assertHasNoErrors();

    expect($rolObjetivo->permisos()->pluck('permisos.id')->all())->toBe([$ver]);
    $this->actingAs($usuario->fresh())->get('/admin/usuarios')->assertOk();

    Livewire::actingAs($actor)->test(Permisos::class, ['rol' => $rolObjetivo])
        ->set('seleccionados', [])->call('guardar')->assertHasNoErrors();
    $this->actingAs($usuario->fresh())->get('/admin/usuarios')->assertForbidden();
});

it('no permite dejar al sistema sin cuentas administradoras', function (string $quitar) {
    $admin = usuarioConPermisos(['roles.ver', 'usuarios.editar', 'roles.asignar_permisos']);
    $antes = $admin->rol->permisos()->pluck('permisos.id')->sort()->values()->all();
    $nuevos = $admin->rol->permisos()->where('identificador', '!=', $quitar)->pluck('permisos.id')->all();

    Livewire::actingAs($admin)->test(Permisos::class, ['rol' => $admin->rol])
        ->set('seleccionados', $nuevos)->call('guardar')->assertHasErrors('seleccionados');

    expect($admin->rol->permisos()->pluck('permisos.id')->sort()->values()->all())->toBe($antes);
})->with(['usuarios.editar', 'roles.asignar_permisos']);

it('permite quitar permisos de administración si queda otra cuenta administradora', function () {
    $admin = usuarioConPermisos(['roles.ver', 'usuarios.editar', 'roles.asignar_permisos']);
    $otroAdmin = usuarioConPermisos(['usuarios.editar', 'roles.asignar_permisos']);

    Livewire::actingAs($admin)->test(Permisos::class, ['rol' => $admin->rol])
        ->set('seleccionados', [permisoId('roles.ver'), permisoId('roles.asignar_permisos')])
        ->call('guardar')->assertHasNoErrors();

    expect($otroAdmin->fresh()->tienePermiso('usuarios.editar'))->toBeTrue();
});

it('rechaza ids de permiso inexistentes', function () {
    $actor = usuarioConPermisos(['roles.ver', 'roles.asignar_permisos']);
    $rol = Rol::factory()->create();

    Livewire::actingAs($actor)->test(Permisos::class, ['rol' => $rol])
        ->set('seleccionados', [999999])->call('guardar')->assertHasErrors('seleccionados.0');

    expect($rol->permisos()->count())->toBe(0);
});

it('deniega guardar con solo roles.ver', function () {
    $actor = usuarioConPermisos(['roles.ver']);
    $rol = Rol::factory()->create();

    Livewire::actingAs($actor)->test(Permisos::class, ['rol' => $rol])
        ->set('seleccionados', [permisoId('usuarios.ver')])->call('guardar')->assertForbidden();

    expect($rol->permisos()->count())->toBe(0);
});
