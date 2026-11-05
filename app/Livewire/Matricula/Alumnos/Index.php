<?php

namespace App\Livewire\Matricula\Alumnos;

use App\Matricula\AlumnoService;
use App\Matricula\EstadoMatricula;
use App\Matricula\MatriculaIncumplida;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $busqueda = '';

    /** '' todos · '1' activos · '0' desactivados */
    #[Url]
    public string $filtroActivo = '';

    /** '' todos · estado de la matrícula del año vigente · 'sin_matricula' */
    #[Url]
    public string $filtroEstado = '';

    public function mount(): void
    {
        $this->authorize('matricula.ver_todas');
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroActivo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function desactivar(int $id, AlumnoService $servicio): void
    {
        $this->accion(fn () => $servicio->desactivar(Alumno::findOrFail($id)));
    }

    public function reactivar(int $id, AlumnoService $servicio): void
    {
        $this->accion(fn () => $servicio->reactivar(Alumno::findOrFail($id)));
    }

    public function eliminar(int $id, AlumnoService $servicio): void
    {
        $this->accion(fn () => $servicio->eliminar(Alumno::findOrFail($id)));
    }

    private function accion(callable $accion): void
    {
        $this->authorize('matricula.gestionar');
        $this->resetErrorBag('general');

        try {
            $accion();
        } catch (MatriculaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render(): View
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();

        return view('livewire.matricula.alumnos.index', [
            'vigente' => $vigente,
            'estados' => EstadoMatricula::cases(),
            'alumnos' => Alumno::query()->with('tipoDocumento')->withExists('matriculas')
                ->buscar($this->busqueda)
                ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
                ->when($this->filtroEstado === 'sin_matricula', fn ($q) => $q->whereDoesntHave('matriculas', fn ($m) => $m->where('anio_id', $vigente?->id ?? 0)))
                ->when(EstadoMatricula::tryFrom($this->filtroEstado), fn ($q, EstadoMatricula $e) => $q->whereHas('matriculas', fn ($m) => $m->where('anio_id', $vigente?->id ?? 0)->where('estado', $e->value)))
                ->orderBy('apellidos')->orderBy('nombres')->orderBy('id')->paginate(15),
        ]);
    }
}
