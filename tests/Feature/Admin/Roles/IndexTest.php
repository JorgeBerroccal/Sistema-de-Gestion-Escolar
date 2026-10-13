<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;

it('lista cada rol con su cantidad de usuarios y de permisos', function () {
    $rol = Rol::factory()->create(['nombre' => 'Rol Contable']);
    $rol->permisos()->sync(Permiso::factory()->count(3)->create()->pluck('id'));
    Usuario::factory()->count(2)->create(['rol_id' => $rol->id]);

    $actor = usuarioConPermisos(['roles.ver']);

    $this->actingAs($actor)->get('/admin/roles')
        ->assertOk()
        ->assertSee('Rol Contable')
        ->assertSeeInOrder(['Rol Contable', '2', '3'])
        ->assertSee(route('admin.roles.permisos', $rol), false);
});

it('deniega la lista sin roles.ver', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/admin/roles')->assertForbidden();
});
