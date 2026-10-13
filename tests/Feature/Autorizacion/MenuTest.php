<?php

it('un usuario sin permisos solo ve Inicio', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/inicio')
        ->assertOk()
        ->assertSee('Inicio')
        ->assertDontSee('Usuarios')
        ->assertDontSee('Roles')
        ->assertDontSee('Permisos');
});

it('con usuarios.ver se ve el enlace Usuarios hacia /admin/usuarios', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.ver']))->get('/inicio')
        ->assertOk()
        ->assertSee('href="'.url('/admin/usuarios').'"', false)
        ->assertSee('>Usuarios<', false);
});

it('sin usuarios.ver no se ve el enlace Usuarios', function () {
    $this->actingAs(usuarioConPermisos(['roles.ver']))->get('/inicio')
        ->assertOk()
        ->assertDontSee('>Usuarios<', false);
});
