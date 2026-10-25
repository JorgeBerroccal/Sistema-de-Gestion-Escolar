<?php

namespace App\Livewire\Academico\AniosEscolares;

use App\Academico\AnioEscolarService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
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

    public function desactivar(int $id, AnioEscolarService $servicio): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $this->ejecutar(fn () => $servicio->desactivar(AnioEscolar::findOrFail($id)));
    }

    public function reactivar(int $id): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        AnioEscolar::findOrFail($id)->update(['activo' => true]);
    }

    public function eliminar(int $id, AnioEscolarService $servicio): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $this->ejecutar(fn () => $servicio->eliminar(AnioEscolar::findOrFail($id)));
    }

    private function ejecutar(callable $accion): void
    {
        try {
            $accion();
        } catch (ReglaAcademicaIncumplida $e) {
            $this->addError('general', $e->getMessage());
        } catch (QueryException) {
            $this->addError('general', 'No se puede eliminar porque tiene datos asociados; desactívalo para conservar el historial.');
        }
    }

    public function render(): View
    {
        return view('livewire.academico.anios-escolares.index', [
            'anios' => AnioEscolar::query()->withCount('periodos')->withExists('secciones')->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate(15),
        ]);
    }
}
