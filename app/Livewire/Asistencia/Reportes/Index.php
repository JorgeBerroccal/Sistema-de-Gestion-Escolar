<?php

namespace App\Livewire\Asistencia\Reportes;

use App\Asistencia\AsistenciaService;
use App\Asistencia\ResumenAsistencia;
use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Reporte de asistencia de una sección en un rango o periodo: totales, porcentaje y ranking de faltas. */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $seccion = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public string $periodo = '';

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('asistencia.ver_todas') || $usuario->tienePermiso('asistencia.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a los reportes de asistencia.');
        }

        if ($this->seccion !== '') {
            // Una sección ajena da 403 sin revelar datos.
            $elegida = Seccion::query()->find((int) $this->seccion);
            if (! $elegida || ! app(AsistenciaService::class)->puedeVerReporte($usuario, $elegida)) {
                throw new AccessDeniedHttpException('No tienes acceso al reporte de esta sección.');
            }
        }
    }

    public function updatedSeccion(): void
    {
        $this->mount();
        $this->desde = $this->hasta = $this->periodo = '';
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $p = $this->periodo === '' ? null : PeriodoEvaluacion::query()->find((int) $this->periodo);

        if ($p) {
            $this->desde = $p->fecha_inicio->toDateString();
            $this->hasta = $p->fecha_fin->toDateString();
        }
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    private function fecha(string $valor, CarbonImmutable $defecto): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? CarbonImmutable::parse($valor) : $defecto;
    }

    public function render(ResumenAsistencia $resumen): View
    {
        $usuario = Auth::user();
        $secciones = Seccion::query()->with(['grado.nivel', 'anio'])
            ->when(! $usuario->tienePermiso('asistencia.ver_todas'), fn ($q) => $q->whereIn('secciones.id', MatriculaRegla::seccionesDelDocente($usuario)->select('seccion_id')))
            ->join('anios_escolares', 'anios_escolares.id', '=', 'secciones.anio_escolar_id')
            ->orderByDesc('anios_escolares.fecha_inicio')->orderBy('secciones.id')
            ->select('secciones.*')->limit(200)->get();

        $elegida = $this->seccion !== '' ? $secciones->firstWhere('id', (int) $this->seccion) : $secciones->first();
        $datos = ['secciones' => $secciones, 'elegida' => $elegida, 'periodos' => collect(), 'resumen' => null, 'ranking' => null];

        if ($elegida) {
            $anio = $elegida->anio;
            // El rango se limita al año de la sección.
            $desde = $this->fecha($this->desde, $anio->fecha_inicio->toImmutable())->max($anio->fecha_inicio->toImmutable());
            $hasta = $this->fecha($this->hasta, CarbonImmutable::parse(min(app(AsistenciaService::class)->hoy(), $anio->fecha_fin->toDateString())))->min($anio->fecha_fin->toImmutable());

            $datos = array_merge($datos, [
                'rangoDesde' => $desde, 'rangoHasta' => $hasta,
                'periodos' => $anio->periodos()->orderBy('orden')->get(),
                'resumen' => $resumen->deSeccion($elegida, $desde, $hasta),
                'ranking' => $resumen->rankingFaltas($elegida, $desde, $hasta),
            ]);
        }

        return view('livewire.asistencia.reportes.index', $datos);
    }
}
