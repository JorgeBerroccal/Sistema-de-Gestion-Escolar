<?php

namespace App\Http\Middleware;

use App\Http\Requests\Auth\LoginRequest;
use App\Support\Sesion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario && ! $usuario->activo) {
            Sesion::cerrarYReiniciar($request);

            return redirect()->route('login')
                ->withErrors(['login' => LoginRequest::MENSAJE_INACTIVA]);
        }

        return $next($request);
    }
}
