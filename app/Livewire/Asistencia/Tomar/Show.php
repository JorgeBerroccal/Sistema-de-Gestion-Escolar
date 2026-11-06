<?php

namespace App\Livewire\Asistencia\Tomar;

use App\Asistencia\AsistenciaIncumplida;
use App\Asistencia\AsistenciaService;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Seccion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Lista de asistencia de una sección en una fecha: estados por alumno, guardado en una sola operación. */
class Show extends Component
{
    public Seccion $seccion;

    #[Url]
    public string $fecha = '';

    /** @var array<int, array{estado_id: int|string, observacion: string}> por `alumno_id` */
    public array $filas = [];

    public string $mensaje = '';

    /** Registro que se está justificando y su motivo. */
    public ?int $justificandoId = null;

    public string $motivo = '';

    public function mount(Seccion $seccion, AsistenciaService $servicio): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('asistencia.gestionar') || $usuario->tienePermiso('asistencia.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a la toma de asistencia.');
        }

        // La pertenencia se comprueba antes de cargar relaciones: una sección ajena no revela datos.
        $servicio->exigirEscritura($usuario, $seccion);

        $this->seccion = $seccion->load(['grado.nivel', 'anio']);
        $this->fecha = $this->fechaValida() ? $this->fecha : $servicio->hoy();
        $this->cargar();
    }

    public function updatedFecha(): void
    {
        $this->fecha = $this->fechaValida() ? $this->fecha : app(AsistenciaService::class)->hoy();
        $this->mensaje = '';
        $this->resetErrorBag();
        $this->cargar();
    }

    public function guardar(AsistenciaService $servicio): void
    {
        $this->resetErrorBag();
        $this->mensaje = '';

        try {
            $total = $servicio->guardar($this->seccion, $this->fechaCarbon(), $this->filas, Auth::user());
            $this->mensaje = "Asistencia guardada ({$total} alumnos).";
            $this->cargar();
        } catch (AsistenciaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function iniciarJustificacion(int $asistenciaId): void
    {
        $this->resetErrorBag();
        $this->motivo = '';
        $this->justificandoId = $asistenciaId;
    }

    public function cancelarJustificacion(): void
    {
        $this->justificandoId = null;
        $this->motivo = '';
    }

    public function justificar(AsistenciaService $servicio): void
    {
        $this->resetErrorBag();

        try {
            $servicio->justificar($this->registroDeLaSeccion((int) $this->justificandoId), $this->motivo, Auth::user());
            $this->mensaje = 'Falta justificada.';
            $this->cancelarJustificacion();
            $this->cargar();
        } catch (AsistenciaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function revertir(int $asistenciaId, AsistenciaService $servicio): void
    {
        $this->resetErrorBag();

        try {
            $servicio->revertir($this->registroDeLaSeccion($asistenciaId), Auth::user());
            $this->mensaje = 'Justificación revertida.';
            $this->cargar();
        } catch (AsistenciaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    /** Solo registros de esta sección: un id ajeno no se puede justificar desde aquí. */
    private function registroDeLaSeccion(int $id): Asistencia
    {
        return Asistencia::query()->where('seccion_id', $this->seccion->id)->with('estado')->findOrFail($id);
    }

    private function fechaValida(): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fecha) === 1 && checkdate((int) substr($this->fecha, 5, 2), (int) substr($this->fecha, 8, 2), (int) substr($this->fecha, 0, 4));
    }

    private function fechaCarbon(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha);
    }

    /** Estado inicial de cada alumno: el registro guardado o «presente». */
    private function cargar(): void
    {
        $servicio = app(AsistenciaService::class);
        $fecha = $this->fechaCarbon();
        $registros = $servicio->registrosDeLaFecha($this->seccion, $fecha);
        $presente = EstadoAsistencia::query()->where('codigo', 'presente')->value('id') ?? EstadoAsistencia::query()->activos()->value('id');

        $this->filas = $servicio->nominaDeLaFecha($this->seccion, $fecha)->mapWithKeys(fn ($alumno) => [
            $alumno->id => [
                'estado_id' => $registros->get($alumno->id)?->estado_id ?? $presente,
                'observacion' => $registros->get($alumno->id)?->observacion ?? '',
            ],
        ])->all();
    }

    public function render(AsistenciaService $servicio): View
    {
        $fecha = $this->fechaCarbon();

        try {
            $servicio->validarFecha($this->seccion, $fecha);
            $aviso = null;
        } catch (AsistenciaIncumplida $e) {
            $aviso = $e->getMessage();
        }

        $estados = EstadoAsistencia::query()->activos()->get();

        return view('livewire.asistencia.tomar.show', [
            'justificables' => $estados->whereNotNull('justificado_id')->pluck('id')->all(),
            'justificados' => $estados->pluck('justificado_id')->filter()->values()->all(),
            'aviso' => $aviso,
            'estados' => $estados,
            'alumnos' => $servicio->nominaDeLaFecha($this->seccion, $fecha),
            'registros' => $servicio->registrosDeLaFecha($this->seccion, $fecha),
            'hoy' => $servicio->hoy(),
        ]);
    }
}
