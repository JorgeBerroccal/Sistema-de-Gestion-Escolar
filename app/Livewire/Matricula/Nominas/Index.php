<?php

namespace App\Livewire\Matricula\Nominas;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Matricula\EstadoMatricula;
use App\Models\AnioEscolar;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Secciones con nómina visibles para el usuario: todas, o solo las que tienen una asignación suya (docente). */
class Index extends Component
{
    use WithPagination;

    /** Id del año; null = año vigente; '0' = todos los años. */
    #[Url]
    public ?string $filtroAnio = null;

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('matricula.ver_todas') || $usuario->tienePermiso('matricula.ver_nomina_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a las nóminas.');
        }
    }

    public function updatingFiltroAnio(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $veTodas = $usuario->tienePermiso('matricula.ver_todas');
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $anioId = $this->filtroAnio ?? (string) ($vigente?->id ?? 0);

        return view('livewire.matricula.nominas.index', [
            'veTodas' => $veTodas,
            'vigente' => $vigente,
            'anioId' => $anioId,
            'anios' => AnioEscolar::query()->orderByDesc('fecha_inicio')->get(),
            'secciones' => Seccion::query()->with(['grado.nivel', 'anio'])
                ->withCount(['matriculas as matriculados_count' => fn ($q) => $q->where('estado', EstadoMatricula::Matriculado->value)])
                ->when(! $veTodas, fn ($q) => $q->whereIn('secciones.id', MatriculaRegla::seccionesDelDocente($usuario)))
                ->when($anioId !== '0', fn ($q) => $q->where('anio_escolar_id', $anioId))
                ->orderBy('anio_escolar_id')->orderBy('grado_id')->orderBy('identificador')->paginate(15),
        ]);
    }
}
