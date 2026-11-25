<?php

require_once __DIR__.'/Ayudas.php';

it('un Docente recibe 403 en la boleta y en la administración de usuarios', function () {
    $b = escenarioBoleta();

    ingresar($b['docente']);

    visit(route('boletas.descargar', [$b['ana']->id, $b['periodo']->id]))
        ->assertSee('No tienes permiso');

    visit(route('admin.usuarios.index'))->assertSee('No tienes permiso');
});

it('un Padre recibe 403 en la boleta de un alumno que no es su hijo', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['boletas.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    ingresar($padre);

    visit(route('boletas.descargar', [$b['beto']->id, $b['periodo']->id]))
        ->assertSee('No tienes permiso');
});
