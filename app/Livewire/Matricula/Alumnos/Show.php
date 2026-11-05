<?php

namespace App\Livewire\Matricula\Alumnos;

use App\Matricula\AlumnoService;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Seccion;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Ficha del alumno con su historial de matrículas; las acciones exigen `matricula.gestionar`. */
class Show extends Component
{
    use AuthorizesRequests;

    public Alumno $alumno;

    public string $seccionId = '';

    /** Matrícula sobre la que se abrió el formulario de retiro o traslado. */
    public ?int $cambioMatriculaId = null;

    /** 'retirar' o 'trasladar' */
    public string $cambioTipo = '';

    public string $cambioFecha = '';

    public string $cambioMotivo = '';

    public string $mensaje = '';

    public function mount(Alumno $alumno): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('matricula.ver_todas') || $usuario->tienePermiso('matricula.ver_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a los alumnos.');
        }

        // La pertenencia se comprueba antes de cargar relaciones: un alumno ajeno no revela datos.
        $alumno->autorizarPertenencia($usuario);

        $this->alumno = $alumno;
    }

    public function matricular(MatriculaService $servicio): void
    {
        $this->ejecutar(function () use ($servicio) {
            $seccion = Seccion::find($this->seccionId) ?? throw new MatriculaIncumplida('Elige una sección.');
            $servicio->matricular($this->alumno, $seccion);
            $this->seccionId = '';

            return 'Alumno matriculado.';
        });
    }

    public function iniciarCambio(int $matriculaId, string $tipo): void
    {
        $this->authorize('matricula.gestionar');
        abort_unless(in_array($tipo, ['retirar', 'trasladar'], true), 422);

        $this->alumno->matriculas()->findOrFail($matriculaId);
        $this->cambioMatriculaId = $matriculaId;
        $this->cambioTipo = $tipo;
        $this->cambioFecha = now()->toDateString();
        $this->cambioMotivo = '';
        $this->resetErrorBag();
    }

    public function cancelarCambio(): void
    {
        $this->cambioMatriculaId = null;
        $this->cambioTipo = '';
    }

    public function confirmarCambio(MatriculaService $servicio): void
    {
        $this->ejecutar(function () use ($servicio) {
            $matricula = $this->alumno->matriculas()->findOrFail($this->cambioMatriculaId);
            $fecha = date_create($this->cambioFecha) ? Carbon::parse($this->cambioFecha) : throw new MatriculaIncumplida('La fecha no es válida.');
            $motivo = $this->cambioMotivo === '' ? null : $this->cambioMotivo;

            $this->cambioTipo === 'trasladar'
                ? $servicio->trasladar($matricula, $fecha, $motivo)
                : $servicio->retirar($matricula, $fecha, $motivo);

            $this->cancelarCambio();

            return 'Estado de la matrícula actualizado.';
        });
    }

    public function corregir(int $matriculaId, MatriculaService $servicio): void
    {
        $this->ejecutar(function () use ($matriculaId, $servicio) {
            $servicio->corregir($this->alumno->matriculas()->findOrFail($matriculaId));

            return 'Matrícula corregida a «matriculado».';
        });
    }

    public function desactivar(AlumnoService $servicio): void
    {
        $this->ejecutar(function () use ($servicio) {
            $servicio->desactivar($this->alumno);

            return 'Alumno desactivado.';
        });
    }

    public function reactivar(AlumnoService $servicio): void
    {
        $this->ejecutar(function () use ($servicio) {
            $servicio->reactivar($this->alumno);

            return 'Alumno reactivado.';
        });
    }

    public function eliminar(AlumnoService $servicio)
    {
        $this->authorize('matricula.gestionar');
        $this->resetErrorBag('general');

        try {
            $servicio->eliminar($this->alumno);
        } catch (MatriculaIncumplida $e) {
            $this->addError('general', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('matricula.alumnos.index', navigate: true);
    }

    /** @param  callable(): string  $accion devuelve el mensaje de éxito */
    protected function ejecutar(callable $accion): void
    {
        $this->authorize('matricula.gestionar');
        $this->resetErrorBag();
        $this->mensaje = '';

        try {
            $this->mensaje = $accion();
        } catch (MatriculaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }

        $this->alumno->refresh();
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $puedeGestionar = $usuario->tienePermiso('matricula.gestionar');
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();

        return view('livewire.matricula.alumnos.show', [
            'puedeGestionar' => $puedeGestionar,
            'vigente' => $vigente,
            'ficha' => $this->alumno->load('tipoDocumento'),
            'historial' => Matricula::query()->where('alumno_id', $this->alumno->id)
                ->with(['seccion.grado.nivel', 'seccion.anio'])
                ->orderByDesc('anio_id')->orderByDesc('id')->get(),
            'secciones' => $puedeGestionar && $vigente
                ? Seccion::query()->with('grado.nivel')->where('anio_escolar_id', $vigente->id)->where('activo', true)
                    ->orderBy('grado_id')->orderBy('identificador')->get()
                : collect(),
        ]);
    }
}
