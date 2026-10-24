<?php

namespace App\Livewire\Academico\AniosEscolares;

use App\Academico\AnioEscolarService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('academico.ver');
    }

    public function marcarVigente(int $id, AnioEscolarService $servicio): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        try {
            $servicio->marcarVigente(AnioEscolar::findOrFail($id));
        } catch (ReglaAcademicaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.academico.anios-escolares.index', [
            'anios' => AnioEscolar::query()->withCount('periodos')->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate(15),
        ]);
    }
}
