<?php

require_once __DIR__.'/Ayudas.php';

it('el Padre pregunta al asistente y recibe la respuesta del proveedor falso', function () {
    $e = escenarioAsistente();

    ingresar($e['padre']);

    visit(route('asistente.index'))
        ->assertSee('Tu pregunta')
        ->fill('#mensaje', '¿Cómo va Ana en sus notas?')
        ->press('Enviar')
        ->assertSee('Respuesta de prueba en español');
});
