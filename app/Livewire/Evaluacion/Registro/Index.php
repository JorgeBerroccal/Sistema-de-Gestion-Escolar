<?php

namespace App\Livewire\Evaluacion\Registro;

use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Pares (sección, curso) del año vigente donde el usuario registra notas: todos los del plan de estudios del grado
 * (`notas.gestionar`) o solo los que tiene asignados (`notas.registrar_propia`).
 */
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('notas.gestionar') || $usuario->tienePermiso('notas.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso al registro de notas.');
        }
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();

        $base = DB::table('secciones')
            ->join('grados', 'grados.id', '=', 'secciones.grado_id')
            ->where('secciones.activo', true)
            ->where('secciones.anio_escolar_id', $vigente?->id ?? 0);

        if ($usuario->tienePermiso('notas.gestionar')) {
            $base->join('curso_grado', 'curso_grado.grado_id', '=', 'grados.id')
                ->join('cursos', 'cursos.id', '=', 'curso_grado.curso_id');
        } else {
            $base->join('asignaciones_docentes', fn ($j) => $j->on('asignaciones_docentes.seccion_id', '=', 'secciones.id')
                ->where('asignaciones_docentes.usuario_id', $usuario->id))
                ->join('cursos', 'cursos.id', '=', 'asignaciones_docentes.curso_id');
        }

        $pares = $base
            ->select('secciones.id as seccion_id', 'cursos.id as curso_id', 'grados.nombre as grado', 'secciones.identificador', 'cursos.nombre as curso')
            ->orderBy('grados.nombre')->orderBy('secciones.identificador')->orderBy('cursos.nombre')->orderBy('cursos.id')
            ->paginate(15);

        return view('livewire.evaluacion.registro.index', ['pares' => $pares, 'vigente' => $vigente]);
    }
}
