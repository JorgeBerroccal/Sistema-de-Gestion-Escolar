<?php

namespace App\Livewire\Evaluacion\Cierres;

use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Estado de cada periodo por sección del año vigente; el Administrador cierra (publica) y reabre. */
class Index extends Component
{
    use WithPagination;

    public string $mensaje = '';

    public function mount(): void
    {
        if (! Auth::user()->tienePermiso('notas.cerrar_periodo')) {
            throw new AccessDeniedHttpException('No puedes cerrar ni reabrir periodos.');
        }
    }

    public function cerrar(int $seccionId, int $periodoId, EvaluacionService $servicio): void
    {
        $this->ejecutar(fn (Seccion $s, PeriodoEvaluacion $p) => $servicio->cerrarPeriodo($s, $p, Auth::user()), $seccionId, $periodoId, 'Periodo cerrado y publicado.');
    }

    public function reabrir(int $seccionId, int $periodoId, EvaluacionService $servicio): void
    {
        $this->ejecutar(fn (Seccion $s, PeriodoEvaluacion $p) => $servicio->reabrirPeriodo($s, $p, Auth::user()), $seccionId, $periodoId, 'Periodo reabierto.');
    }

    private function ejecutar(\Closure $accion, int $seccionId, int $periodoId, string $exito): void
    {
        $this->resetErrorBag();
        $this->mensaje = '';

        try {
            $accion(Seccion::query()->with('anio')->findOrFail($seccionId), PeriodoEvaluacion::query()->findOrFail($periodoId));
            $this->mensaje = $exito;
        } catch (EvaluacionIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render(): View
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $periodos = $vigente ? PeriodoEvaluacion::query()->where('anio_escolar_id', $vigente->id)->orderBy('orden')->get() : collect();

        $secciones = Seccion::query()->with(['grado.nivel'])
            ->where('secciones.activo', true)->where('secciones.anio_escolar_id', $vigente?->id ?? 0)
            ->join('grados', 'grados.id', '=', 'secciones.grado_id')
            ->orderBy('grados.nombre')->orderBy('secciones.identificador')->orderBy('secciones.id')
            ->select('secciones.*')->paginate(15);

        $cierres = CierrePeriodo::query()->with('cerrador')->whereIn('seccion_id', $secciones->pluck('id'))
            ->whereIn('periodo_id', $periodos->pluck('id'))->get()
            ->keyBy(fn (CierrePeriodo $c) => $c->seccion_id.'-'.$c->periodo_id);

        return view('livewire.evaluacion.cierres.index', ['secciones' => $secciones, 'periodos' => $periodos, 'cierres' => $cierres, 'vigente' => $vigente]);
    }
}
