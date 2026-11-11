<?php

namespace App\Http\Controllers;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Evaluacion\BoletaService;
use App\Models\Alumno;
use App\Models\PeriodoEvaluacion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Descarga de la boleta en PDF. Autoriza ANTES de generar (research R-13):
 *  - `boletas.ver_todas` (Administrador, Director): cualquier alumno; un periodo sin cierre sale marcado «BORRADOR».
 *  - `boletas.ver_propia` (Padre, Alumno): solo el propio alumno o un hijo, y solo periodos publicados (cerrados).
 *  - Cualquier otro rol, incluido el Docente: 403 (mínimo privilegio).
 * La autorización no depende de las reglas de pertenencia de notas: la boleta incluye todos los cursos.
 */
class BoletaController extends Controller
{
    public function descargar(Request $request, int $estudiante, string $periodo, BoletaService $boletas): Response
    {
        $usuario = $request->user();
        $todas = $usuario->tienePermiso('boletas.ver_todas');
        $propia = $usuario->tienePermiso('boletas.ver_propia');

        if (! $todas && ! $propia) {
            throw new AccessDeniedHttpException('No tienes acceso a las boletas.');
        }

        // Un alumno ajeno es 403 exista o no: no se revela qué ids existen.
        if (! $todas && ! Alumno::query()->whereKey($estudiante)->whereIn('alumnos.id', MatriculaRegla::alumnosPropios($usuario))->exists()) {
            throw new AccessDeniedHttpException('No tienes acceso a la boleta de este alumno.');
        }

        $alumnoModelo = Alumno::query()->findOrFail($estudiante);
        $periodoModelo = $periodo === 'anual' ? null : PeriodoEvaluacion::query()->findOrFail($periodo);

        // La familia solo recibe periodos publicados; Administrador y Director también borradores.
        $soloPublicados = ! $todas;
        $datos = $boletas->datos($alumnoModelo, $periodoModelo, $soloPublicados);

        if ($datos === null) {
            if ($soloPublicados) {
                throw new AccessDeniedHttpException('La boleta de este periodo aún no está publicada.');
            }

            abort(404);
        }

        return $boletas->pdf($datos)->download($boletas->nombreArchivo($alumnoModelo, $periodoModelo));
    }
}
