<?php

namespace App\Livewire\Academico\Asignaciones;

use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Sirve «Asignaciones» y «Mis asignaciones»: la consulta siempre pasa por `visiblePara()`. */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $filtroAnio = '';

    #[Url]
    public string $filtroSeccion = '';

    #[Url]
    public string $filtroCurso = '';

    #[Url]
    public string $filtroDocente = '';

    #[Url]
    public bool $soloPendientes = false;

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('asignaciones.ver_todas') || $usuario->tienePermiso('asignaciones.ver_propias'))) {
            throw new AccessDeniedHttpException('No tienes acceso a las asignaciones.');
        }
    }

    public function updatingFiltroAnio(): void
    {
        $this->resetPage();
        $this->filtroSeccion = '';
    }

    public function updatingFiltroSeccion(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroCurso(): void
    {
        $this->resetPage();
    }

    public function updatingSoloPendientes(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroDocente(): void
    {
        $this->resetPage();
    }

    public function quitar(int $id, AsignacionService $servicio): void
    {
        $this->authorize('asignaciones.gestionar');
        $this->resetErrorBag('general');

        try {
            $servicio->quitar(AsignacionDocente::findOrFail($id));
        } catch (ReglaAcademicaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $veTodas = $usuario->tienePermiso('asignaciones.ver_todas');
        $prefijo = $veTodas ? trim($this->filtroDocente) : '';

        return view('livewire.academico.asignaciones.index', [
            'veTodas' => $veTodas,
            'anios' => AnioEscolar::query()->orderByDesc('fecha_inicio')->get(),
            'cursos' => Curso::query()->orderBy('nombre')->get(),
            'secciones' => $this->filtroAnio === '' ? collect() : Seccion::query()->with('grado')
                ->where('anio_escolar_id', $this->filtroAnio)->orderBy('grado_id')->orderBy('identificador')->get(),
            'asignaciones' => AsignacionDocente::query()->visiblePara($usuario)
                ->with(['usuario.rol.permisos', 'curso', 'seccion.grado.nivel', 'seccion.anio'])
                ->when($this->filtroAnio !== '', fn ($q) => $q->whereHas('seccion', fn ($s) => $s->where('anio_escolar_id', $this->filtroAnio)))
                ->when($this->filtroSeccion !== '', fn ($q) => $q->where('seccion_id', $this->filtroSeccion))
                ->when($this->filtroCurso !== '', fn ($q) => $q->where('curso_id', $this->filtroCurso))
                ->when($veTodas && $this->soloPendientes, fn ($q) => $q->where(fn ($w) => $w
                    ->whereHas('usuario', fn ($u) => $u->where('activo', false))
                    ->orWhereDoesntHave('usuario.rol.permisos', fn ($p) => $p->where('identificador', AsignacionDocente::PERMISO_DOCENTE))))
                ->when($prefijo !== '', function ($q) use ($prefijo) {
                    $patron = addcslashes($prefijo, '\\%_').'%';
                    $q->whereHas('usuario', fn ($d) => $d->where(fn ($w) => $w->where('nombres', 'like', $patron)->orWhere('apellidos', 'like', $patron)));
                })
                ->orderByDesc('id')->paginate(15),
        ]);
    }
}
