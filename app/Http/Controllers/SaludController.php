<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;

/**
 * Comprobación de salud pública (/up) con la versión desplegada. Reemplaza la ruta de salud
 * predeterminada de Laravel y vive fuera del grupo `web` (sin sesión ni Redis). Contrato:
 * specs/002-despliegue-ci-staging/contracts/salud.md.
 */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        event(new DiagnosingHealth);

        return response()->json([
            'status' => 'ok',
            'version' => config('app.version'),
        ]);
    }
}
