<?php

require_once __DIR__.'/Ayudas.php';

it('inicia sesión con el nombre de usuario y llega a Inicio', function () {
    $usuario = usuarioConPermisos([]);

    ingresar($usuario)->assertPathIs('/inicio')->assertNoJavaScriptErrors();
});

it('inicia sesión con el correo y llega a Inicio', function () {
    $usuario = usuarioConPermisos([]);

    ingresar($usuario, $usuario->email)->assertPathIs('/inicio');
});

it('rechaza una contraseña incorrecta y se queda en el acceso', function () {
    $usuario = usuarioConPermisos([]);

    visit('/login')
        ->fill('#login', $usuario->username)
        ->fill('#password', 'contraseña-equivocada')
        ->press('Ingresar')
        ->assertPathIs('/login');
});
