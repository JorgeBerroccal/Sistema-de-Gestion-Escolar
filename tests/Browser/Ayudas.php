<?php

use App\Models\Usuario;

/** Inicia sesión por la pantalla de acceso (el navegador no comparte el usuario de `actingAs`). */
function ingresar(Usuario $usuario, ?string $login = null)
{
    return visit('/login')
        ->fill('#login', $login ?? $usuario->username)
        ->fill('#password', 'password')
        ->press('Ingresar');
}
