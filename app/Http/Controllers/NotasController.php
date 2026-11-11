<?php

namespace App\Http\Controllers;

use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\Curso;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Guardado de la tabla de notas como formulario clásico (POST con CSRF), igual que el login (research R-18):
 * es lo que prueba k6 de forma robusta. Livewire se queda con la interacción de la pantalla.
 */
class NotasController extends Controller
{
    public function guardar(Request $request, Seccion $seccion, Curso $curso, EvaluacionService $evaluacion): RedirectResponse
    {
        $usuario = $request->user();

        // La autorización va antes de tocar datos o de revelar si el periodo existe.
        if (! $evaluacion->puedeEscribir($usuario, $seccion, $curso)) {
            throw new AccessDeniedHttpException('No puedes registrar las notas de este curso y sección.');
        }

        $datos = $request->validate([
            'periodo' => ['required', 'integer', 'exists:periodos_evaluacion,id'],
            'notas' => ['nullable', 'array'],
            'notas.*' => ['array'],
            'notas.*.*' => ['nullable', 'string', 'max:20'],
        ]);

        $destino = route('notas.registro.show', ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $datos['periodo']]);

        try {
            $resultado = $evaluacion->guardarNotas(
                $seccion, $curso, PeriodoEvaluacion::query()->findOrFail($datos['periodo']), $datos['notas'] ?? [], $usuario,
            );
        } catch (EvaluacionIncumplida $e) {
            return redirect($destino)->withInput()->withErrors(['notas' => $e->mensajes()]);
        }

        return redirect($destino)->with('estado', "Notas guardadas ({$resultado['guardadas']} registradas, {$resultado['borradas']} vaciadas).");
    }
}
