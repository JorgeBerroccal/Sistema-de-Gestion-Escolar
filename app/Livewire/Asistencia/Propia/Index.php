<?php

namespace App\Livewire\Asistencia\Propia;

use App\Asistencia\ResumenAsistencia;
use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Asistencia del propio Alumno o de los hijos del Padre de Familia: detalle por mes y resumen del año. */
class Index extends Component
{
    /** Id del alumno elegido; vacío = el primero. Uno ajeno da 403. */
    #[Url]
    public string $alumnoId = '';

    /** Mes con formato AAAA-MM; vacío = el actual. */
    #[Url]
    public string $mes = '';

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! $usuario->tienePermiso('asistencia.ver_propia')) {
            throw new AccessDeniedHttpException('No tienes acceso a esta asistencia.');
        }

        $propios = $this->propios()->pluck('id')->all();

        if ($this->alumnoId !== '' && ! in_array((int) $this->alumnoId, $propios, true)) {
            throw new AccessDeniedHttpException('No tienes acceso a la asistencia de este alumno.');
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes) !== 1) {
            $this->mes = now(config('asistencia.zona_horaria'))->format('Y-m');
        }
    }

    public function updatedAlumnoId(): void
    {
        $this->mount();
    }

    private function propios()
    {
        return Alumno::query()->whereIn('alumnos.id', MatriculaRegla::alumnosPropios(Auth::user()))
            ->orderBy('apellidos')->orderBy('nombres')->limit(50);
    }

    public function render(ResumenAsistencia $resumen): View
    {
        $alumnos = $this->propios()->get();
        $elegido = $this->alumnoId !== '' ? $alumnos->firstWhere('id', (int) $this->alumnoId) : $alumnos->first();

        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $desde = $vigente?->fecha_inicio ?? CarbonImmutable::now()->startOfYear();
        $hasta = $vigente?->fecha_fin ?? CarbonImmutable::now()->endOfYear();
        [$anio, $mes] = array_map('intval', explode('-', $this->mes));

        return view('livewire.asistencia.propia.index', [
            'alumnos' => $alumnos,
            'elegido' => $elegido,
            'vigente' => $vigente,
            'resumen' => $elegido ? $resumen->delAlumno($elegido, $desde, $hasta) : null,
            'registros' => $elegido ? $resumen->porMes($elegido, $anio, $mes) : collect(),
        ]);
    }
}
