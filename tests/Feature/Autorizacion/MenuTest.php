<?php

it('un usuario sin permisos solo ve Inicio', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/inicio')
        ->assertOk()
        ->assertSee('Inicio')
        ->assertDontSee('Usuarios')
        ->assertDontSee('Roles')
        ->assertDontSee('Permisos');
});

it('oculta ítems cuya ruta no existe aunque tenga el permiso', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.ver', 'roles.ver']))->get('/inicio')
        ->assertOk()
        ->assertDontSee('>Usuarios<', false)
        ->assertDontSee('>Roles<', false);
});
