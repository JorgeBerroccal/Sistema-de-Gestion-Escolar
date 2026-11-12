<?php

use Database\Seeders\RolesYPermisosSeeder;

it('el menú muestra «Asistente virtual» solo a quien tiene el permiso', function () {
    $con = usuarioConPermisos(['asistente.usar']);
    $sin = usuarioConPermisos(['notas.ver_propia']);

    $this->actingAs($con)->get('/inicio')->assertOk()->assertSee('Asistente virtual');
    $this->actingAs($sin)->get('/inicio')->assertOk()->assertDontSee('Asistente virtual');
});

it('con los roles del seeder lo ven Padre de Familia y Alumno, y no Docente, Director ni Administrador', function () {
    $this->seed(RolesYPermisosSeeder::class);

    foreach (['Padre de Familia', 'Alumno'] as $rol) {
        $this->actingAs(usuarioConRolDeSeeder($rol))->get('/inicio')->assertOk()->assertSee('Asistente virtual');
    }

    foreach (['Docente', 'Director', 'Administrador'] as $rol) {
        $this->actingAs(usuarioConRolDeSeeder($rol))->get('/inicio')->assertOk()->assertDontSee('Asistente virtual');
    }
});
