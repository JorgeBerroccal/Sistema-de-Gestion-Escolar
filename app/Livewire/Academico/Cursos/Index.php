<?php

namespace App\Livewire\Academico\Cursos;

use App\Livewire\Academico\Concerns\CambiaEstado;
use App\Models\Curso;
use App\Models\Nivel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use CambiaEstado;
    use WithPagination;

    #[Url]
    public string $filtroNivel = '';

    protected function modelo(): string
    {
        return Curso::class;
    }

    public function mount(): void
    {
        $this->authorize('academico.ver');
    }

    public function updatingFiltroNivel(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.academico.cursos.index', [
            'niveles' => Nivel::query()->orderBy('orden')->get(),
            'cursos' => Curso::query()->with(['nivel', 'grados' => fn ($q) => $q->orderBy('orden')])->withExists('asignaciones')
                ->when($this->filtroNivel !== '', fn ($q) => $q->where('nivel_id', $this->filtroNivel))
                ->orderBy('nivel_id')->orderBy('nombre')->paginate(15),
        ]);
    }
}
