<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CuentaController extends Controller
{
    public function mostrar(Request $request): View
    {
        return view('cuenta.password', ['usuario' => $request->user()]);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:password_actual'],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);

        $usuario = $request->user();
        $usuario->forceFill(['password' => $datos['password'], 'debe_cambiar_password' => false])->save();

        // Cierra las demás sesiones del usuario y deja vigente la actual.
        Auth::logoutOtherDevices($datos['password']);
        $request->session()->regenerate();

        return redirect()->route('inicio')->with('exito', 'Tu contraseña se cambió correctamente.');
    }
}
