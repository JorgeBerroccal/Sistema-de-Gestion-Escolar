<?php

namespace App\Livewire\Asistencia\Reportes;

use App\Asistencia\AsistenciaService;
use App\Asistencia\ResumenAsistencia;
use App\Models\Alumno as AlumnoModelo;
use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Seccion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Detalle de un alumno en el reporte de una sección: sus días y estados en el rango. */
class Alumno extends Component
{
    use WithPagination;

    public Seccion $seccion;

    public AlumnoModelo $alumno;

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(Seccion $seccion, AlumnoModelo $alumno, AsistenciaService $servicio): void
    {
        $usuario = Auth::user();

        if (! $servicio->puedeVerReporte($usuario, $seccion)) {
            throw new AccessDeniedHttpException('No tienes acceso al reporte de esta sección.');
        }

        // El alumno debe pertenecer a la sección (matrícula o registros); si no, 403 sin revelar datos.
        $pertenece = Matricula::query()->where('seccion_id', $seccion->id)->where('alumno_id', $alumno->id)->exists()
            || Asistencia::query()->where('seccion_id', $seccion->id)->where('alumno_id', $alumno->id)->exists();

        if (! $pertenece) {
            throw new AccessDeniedHttpException('El alumno no pertenece a esta sección.');
        }

        $this->seccion = $seccion->load(['grado.nivel', 'anio']);
        $this->alumno = $alumno;
    }

    private function fecha(string $valor, CarbonImmutable $defecto): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? CarbonImmutable::parse($valor) : $defecto;
    }

    public function render(ResumenAsistencia $resumen): View
    {
        $anio = $this->seccion->anio;
        $desde = $this->fecha($this->desde, $anio->fecha_inicio->toImmutable())->max($anio->fecha_inicio->toImmutable());
        $hasta = $this->fecha($this->hasta, $anio->fecha_fin->toImmutable())->min($anio->fecha_fin->toImmutable());

        return view('livewire.asistencia.reportes.alumno', [
            'rangoDesde' => $desde,
            'rangoHasta' => $hasta,
            'resumen' => $resumen->delAlumno($this->alumno, $desde, $hasta, $this->seccion),
            'registros' => Asistencia::query()->with('estado')
                ->where('seccion_id', $this->seccion->id)->where('alumno_id', $this->alumno->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->orderByDesc('fecha')->paginate(31),
        ]);
    }
}
