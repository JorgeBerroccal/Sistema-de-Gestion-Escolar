<?php

namespace App\Livewire\Evaluacion\Propia;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionIncumplida;
use App\Models\Alumno;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Notas propias (Alumno) o de los hijos (Padre de Familia) de periodos publicados, con promedio por periodo y
 * anual. Pertenencia: solo alumnos propios o hijos; un alumno ajeno por la URL o por manipular el componente → 403.
 */
class Index extends Component
{
    #[Url]
    public ?int $hijo = null;

    /** Id del periodo o `anual`. */
    #[Url]
    public string $periodo = '';

    public function mount(): void
    {
        if (! Auth::user()->tienePermiso('notas.ver_propia')) {
            throw new AccessDeniedHttpException('No tienes acceso a las notas.');
        }

        $hijos = $this->hijos();

        if ($this->hijo !== null) {
            $this->exigirPropio($this->hijo);
        } else {
            $this->hijo = $hijos->first()?->id;
        }
    }

    public function updatedHijo(): void
    {
        $this->exigirPropio((int) $this->hijo);
        $this->periodo = '';
    }

    private function exigirPropio(int $alumnoId): void
    {
        if (! $this->hijos()->contains('id', $alumnoId)) {
            throw new AccessDeniedHttpException('No tienes acceso a las notas de este alumno.');
        }
    }

    /** @return Collection<int, Alumno> */
    private function hijos()
    {
        return Alumno::query()->whereIn('alumnos.id', MatriculaRegla::alumnosPropios(Auth::user()))
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('alumnos.id')->limit(50)->get();
    }

    public function render(ConsultaNotas $consulta): View
    {
        $usuario = Auth::user();
        $hijos = $this->hijos();
        $alumno = $hijos->firstWhere('id', $this->hijo);
        $periodos = $alumno ? $consulta->periodosPublicados($alumno) : collect();
        $aviso = null;
        $datos = null;
        $anual = null;

        if ($alumno && $periodos->isNotEmpty()) {
            try {
                if ($this->periodo === 'anual') {
                    $anual = $consulta->anualDelAlumno($alumno, $consulta->periodosDelAnioAnual($alumno), $usuario);
                } else {
                    $elegido = $periodos->firstWhere('id', (int) $this->periodo) ?? $periodos->first();
                    $datos = $consulta->delAlumno($alumno, $elegido, $usuario);
                    $this->periodo = (string) $elegido->id;
                }
            } catch (EvaluacionIncumplida $e) {
                $aviso = $e->getMessage();
            }
        }

        return view('livewire.evaluacion.propia.index', [
            'hijos' => $hijos,
            'alumnoActual' => $alumno,
            'periodos' => $periodos,
            'datos' => $datos,
            'anual' => $anual,
            'aviso' => $aviso,
            'hayBoleta' => Route::has('boletas.descargar') && $usuario->tienePermiso('boletas.ver_propia'),
        ]);
    }
}
