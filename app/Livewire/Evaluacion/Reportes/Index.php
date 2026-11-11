<?php

namespace App\Livewire\Evaluacion\Reportes;

use App\Evaluacion\ConsultaNotas;
use App\Models\AnioEscolar;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reporte de notas por sección y periodo, solo lectura. Ven cualquier sección quienes tienen `notas.ver_todas`; el
 * Docente (`notas.registrar_propia`), solo las secciones donde tiene asignación y sus cursos.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public ?int $seccion = null;

    #[Url]
    public ?int $periodo = null;

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('notas.ver_todas') || $usuario->tienePermiso('notas.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a los reportes de notas.');
        }

        $this->exigirSeccionVisible();
    }

    public function updatedSeccion(): void
    {
        $this->exigirSeccionVisible();
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    private function exigirSeccionVisible(): void
    {
        if ($this->seccion !== null && ! $this->seccionesVisibles()->whereKey($this->seccion)->exists()) {
            throw new AccessDeniedHttpException('No tienes acceso a esta sección.');
        }
    }

    /** @return Builder<Seccion> */
    private function seccionesVisibles(): Builder
    {
        $usuario = Auth::user();
        $vigente = AnioEscolar::query()->where('vigente', 1)->value('id');
        $consulta = Seccion::query()->where('secciones.activo', true)->where('secciones.anio_escolar_id', $vigente ?? 0);

        if (! $usuario->tienePermiso('notas.ver_todas')) {
            $consulta->whereIn('secciones.id', DB::table('asignaciones_docentes')
                ->select('seccion_id')->where('usuario_id', $usuario->id));
        }

        return $consulta;
    }

    public function render(ConsultaNotas $consulta): View
    {
        $secciones = $this->seccionesVisibles()->with('grado')
            ->join('grados', 'grados.id', '=', 'secciones.grado_id')
            ->orderBy('grados.nombre')->orderBy('secciones.identificador')->orderBy('secciones.id')
            ->select('secciones.*')->limit(200)->get();

        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $periodos = $vigente ? PeriodoEvaluacion::query()->where('anio_escolar_id', $vigente->id)->orderBy('orden')->get() : collect();

        $seccion = $this->seccion ? $secciones->firstWhere('id', $this->seccion) : null;
        $periodoElegido = $periodos->firstWhere('id', $this->periodo) ?? $periodos->first();
        $reporte = $seccion && $periodoElegido ? $consulta->reporteSeccion($seccion, $periodoElegido, Auth::user()) : null;

        return view('livewire.evaluacion.reportes.index', [
            'secciones' => $secciones, 'periodos' => $periodos, 'periodoElegido' => $periodoElegido, 'reporte' => $reporte,
        ]);
    }
}
