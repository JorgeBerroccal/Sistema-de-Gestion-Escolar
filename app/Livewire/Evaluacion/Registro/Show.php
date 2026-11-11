<?php

namespace App\Livewire\Evaluacion\Registro;

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Notas de un curso y sección en un periodo: editor de criterios (Livewire) y tabla alumno × criterio que se
 * guarda con un formulario clásico POST con CSRF (research R-18). La tabla va en `wire:ignore`: ninguna
 * actualización de Livewire borra lo que el Docente escribió; tras guardar criterios se recarga la página.
 */
class Show extends Component
{
    public Seccion $seccion;

    public Curso $curso;

    #[Url]
    public ?int $periodo = null;

    /** @var list<array{id: int|null, nombre: string, peso: string}> */
    public array $criterios = [];

    public string $mensaje = '';

    public function mount(Seccion $seccion, Curso $curso, EvaluacionService $servicio): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('notas.gestionar') || $usuario->tienePermiso('notas.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso al registro de notas.');
        }

        // La pertenencia se comprueba antes de cargar relaciones: un par ajeno no revela datos.
        if (! $servicio->puedeAbrir($usuario, $seccion, $curso)) {
            throw new AccessDeniedHttpException('No tienes acceso a las notas de este curso y sección.');
        }

        $this->seccion = $seccion->load(['grado.nivel', 'anio']);
        $this->curso = $curso;
        $this->periodo = $this->periodoElegido()?->id;
        $this->cargarCriterios();
    }

    public function agregarCriterio(): void
    {
        $this->criterios[] = ['id' => null, 'nombre' => '', 'peso' => ''];
    }

    public function quitarCriterio(int $indice): void
    {
        unset($this->criterios[$indice]);
        $this->criterios = array_values($this->criterios);
    }

    public function guardarCriterios(EvaluacionService $servicio): void
    {
        $this->resetErrorBag();
        $periodo = $this->periodoElegido();

        if (! $periodo) {
            $this->addError('criterios', 'No hay un periodo de evaluación configurado para el año de la sección.');

            return;
        }

        try {
            $servicio->guardarEsquema($this->seccion, $this->curso, $periodo, $this->criterios, Auth::user());
        } catch (EvaluacionIncumplida $e) {
            $this->addError('criterios', implode(' ', $e->mensajes()));

            return;
        }

        session()->flash('estado', 'Criterios guardados.');
        $this->redirect(route('notas.registro.show', ['seccion' => $this->seccion, 'curso' => $this->curso, 'periodo' => $periodo->id]));
    }

    /** El periodo de la URL si es del año; si no, el último con `fecha_inicio` ≤ hoy y, si ninguno, el primero. */
    private function periodoElegido(): ?PeriodoEvaluacion
    {
        $periodos = PeriodoEvaluacion::query()->where('anio_escolar_id', $this->seccion->anio_escolar_id)->orderBy('orden')->get();

        return $periodos->firstWhere('id', $this->periodo)
            ?? $periodos->filter(fn ($p) => $p->fecha_inicio->toDateString() <= now()->toDateString())->last()
            ?? $periodos->first();
    }

    private function cargarCriterios(): void
    {
        $periodo = $this->periodoElegido();

        $this->criterios = $periodo
            ? app(EvaluacionService::class)->criteriosDelPar($this->seccion, $this->curso, $periodo)
                ->map(fn (CriterioEvaluacion $c) => ['id' => $c->id, 'nombre' => $c->nombre, 'peso' => rtrim(rtrim((string) $c->peso, '0'), '.')])->all()
            : [];
    }

    public function render(EvaluacionService $servicio, ConsultaNotas $consulta): View
    {
        $usuario = Auth::user();
        $periodo = $this->periodoElegido();
        $periodos = PeriodoEvaluacion::query()->where('anio_escolar_id', $this->seccion->anio_escolar_id)->orderBy('orden')->get();
        $guardados = $periodo ? $servicio->criteriosDelPar($this->seccion, $this->curso, $periodo) : collect();
        $cerrado = $periodo && $servicio->periodoCerrado($this->seccion, $periodo);

        $aviso = null;
        $soloLectura = false;
        $escala = null;

        try {
            $escala = $servicio->escala();
        } catch (EvaluacionIncumplida $e) {
            $aviso = $e->getMessage();
            $soloLectura = true;
        }

        if (! $periodo) {
            $aviso = 'No hay periodos de evaluación configurados para el año de la sección.';
            $soloLectura = true;
        } elseif (! $soloLectura) {
            try {
                $servicio->exigirEscritura($usuario, $this->seccion, $this->curso, $periodo);
            } catch (EvaluacionIncumplida $e) {
                $aviso = $e->getMessage();
                $soloLectura = true;
            }
        }

        return view('livewire.evaluacion.registro.show', [
            'periodoActual' => $periodo,
            'periodos' => $periodos,
            'guardados' => $guardados,
            'alumnos' => $servicio->nominaActiva($this->seccion),
            'notas' => $guardados->isEmpty() ? [] : $consulta->notasDelPar($guardados),
            'promedios' => $periodo && $escala ? $consulta->promediosDelPar($this->seccion, $this->curso, $periodo) : [],
            'calculo' => $escala ? $servicio->calculo() : null,
            'cerrado' => $cerrado,
            'soloLectura' => $soloLectura,
            'aviso' => $aviso,
            'escala' => $escala,
            'verBoleta' => $usuario->tienePermiso('boletas.ver_todas') && Route::has('boletas.descargar'),
        ]);
    }
}
