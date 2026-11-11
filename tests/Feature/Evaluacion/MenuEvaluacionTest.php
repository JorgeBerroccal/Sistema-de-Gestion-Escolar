<?php

it('el menú de registro de notas aparece solo con los permisos correspondientes', function () {
    $admin = usuarioConPermisos(['notas.gestionar']);
    $docente = usuarioConPermisos(['notas.registrar_propia']);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $director = usuarioConPermisos(['notas.ver_todas']);

    $this->actingAs($admin)->get('/inicio')->assertOk()->assertSee('Registro de notas')->assertDontSee('Mis notas');
    $this->actingAs($docente)->get('/inicio')->assertOk()->assertSee('Notas de mis cursos')->assertDontSee('Registro de notas');
    $this->actingAs($padre)->get('/inicio')->assertOk()->assertDontSee('Registro de notas')->assertDontSee('Notas de mis cursos');
    $this->actingAs($director)->get('/inicio')->assertOk()->assertDontSee('Registro de notas')->assertDontSee('Notas de mis cursos');
});
