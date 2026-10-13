<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Sesion
{
    /** Cierra la sesión del usuario e invalida la sesión y el token CSRF. */
    public static function cerrarYReiniciar(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
