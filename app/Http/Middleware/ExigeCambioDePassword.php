<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Mientras haya un cambio de contraseña pendiente, solo se permite «Mi cuenta» y cerrar la sesión (R-05). */
class ExigeCambioDePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario?->debe_cambiar_password && ! $request->routeIs('cuenta.*', 'logout')) {
            if ($request->expectsJson() || $request->header('X-Livewire')) {
                abort(403, 'Debes cambiar tu contraseña antes de continuar.');
            }

            return redirect()->route('cuenta.password');
        }

        return $next($request);
    }
}
