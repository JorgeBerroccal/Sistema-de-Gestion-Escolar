<?php

require_once __DIR__.'/Ayudas.php';

it('el Padre ve las notas de su hijo y el enlace a la boleta del periodo publicado', function () {
    $b = escenarioBoleta();
    $padre = usuarioConPermisos(['boletas.ver_propia', 'notas.ver_propia', 'matricula.ver_propia']);
    $b['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    ingresar($padre);

    // La descarga misma (PDF, cabeceras, 403 a ajenos) la cubren las pruebas de `tests/Feature/Evaluacion/Acceso/`;
    // aquí se comprueba lo que solo ve el navegador: que el Padre llegue a la pantalla y encuentre los enlaces de su hijo.
    visit(route('notas.propia.index'))
        ->assertSee('Descargar boleta')
        ->assertPresent('a[href$="/boletas/'.$b['ana']->id.'/'.$b['periodo']->id.'"]')
        ->assertPresent('a[href$="/boletas/'.$b['ana']->id.'/anual"]');
});
