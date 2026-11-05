<?php

namespace App\Livewire\Matricula\Nominas;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Matricula\EstadoMatricula;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Nómina de una sección: lo que ve cada rol sale de `Matricula::visiblePara()`. */
class Show extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public Seccion $seccion;

    #[Url]
    public string $busqueda = '';

    /** '' = todas · 'matriculado' · 'retirado' · 'trasladado' */
    #[Url]
    public string $filtroEstado = '';

    public string $busquedaElegibles = '';

    public string $mensaje = '';

    /** @var array<int, int|string> ids de los alumnos marcados para la matrícula masiva */
    public array $seleccionados = [];

    public function mount(Seccion $seccion): void
    {
        $usuario = Auth::user();
        $veTodas = $usuario->tienePermiso('matricula.ver_todas');

        if (! ($veTodas || $usuario->tienePermiso('matricula.ver_nomina_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a las nóminas.');
        }

        // La pertenencia se comprueba antes de cargar relaciones: una sección ajena no revela datos.
        if (! $veTodas && ! MatriculaRegla::seccionesDelDocente($usuario)->where('seccion_id', $seccion->id)->exists()) {
            throw new AccessDeniedHttpException('No tienes acceso a esta nómina.');
        }

        $this->seccion = $seccion->load(['grado.nivel', 'anio']);
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function matricularSeleccionados(MatriculaService $servicio): void
    {
        $this->authorize('matricula.gestionar');
        $this->resetErrorBag('general');
        $this->mensaje = '';

        try {
            $servicio->matricularVarios($this->seccion, $this->seleccionados);
            $this->seleccionados = [];
            $this->mensaje = 'Alumnos matriculados.';
        } catch (MatriculaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $puedeGestionar = $usuario->tienePermiso('matricula.gestionar');
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $admiteMatricula = $puedeGestionar && $this->seccion->activo && $vigente && $this->seccion->anio_escolar_id === $vigente->id;

        return view('livewire.matricula.nominas.show', [
            'puedeGestionar' => $puedeGestionar,
            'puedeVerFicha' => $usuario->tienePermiso('matricula.ver_todas'),
            'admiteMatricula' => $admiteMatricula,
            'estados' => EstadoMatricula::cases(),
            'matriculas' => Matricula::query()->visiblePara($usuario)
                ->with('alumno.tipoDocumento')
                ->where('matriculas.seccion_id', $this->seccion->id)
                ->when($this->busqueda !== '', fn ($q) => $q->whereIn('matriculas.alumno_id', Alumno::query()->buscar($this->busqueda)->select('alumnos.id')))
                ->when(EstadoMatricula::tryFrom($this->filtroEstado), fn ($q, EstadoMatricula $e) => $q->where('estado', $e->value))
                ->join('alumnos', 'alumnos.id', '=', 'matriculas.alumno_id')
                ->orderBy('alumnos.apellidos')->orderBy('alumnos.nombres')->orderBy('matriculas.id')
                ->select('matriculas.*')->paginate(15),
            'elegibles' => $admiteMatricula
                ? Alumno::query()->with('tipoDocumento')->where('activo', true)
                    ->whereDoesntHave('matriculas', fn ($m) => $m->where('anio_id', $vigente->id)->where('estado', EstadoMatricula::Matriculado->value))
                    ->buscar($this->busquedaElegibles)
                    ->orderBy('apellidos')->orderBy('nombres')->limit(30)->get()
                : collect(),
        ]);
    }
}
