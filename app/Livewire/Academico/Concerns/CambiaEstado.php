<?php

namespace App\Livewire\Academico\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Desactivar, reactivar y eliminar para los listados del módulo (FR-012, FR-013). Eliminar solo si el
 * registro no tiene dependientes; la clave foránea `restrictOnDelete` es la segunda defensa.
 */
trait CambiaEstado
{
    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    /** Motivo por el que no se puede desactivar, o null si se puede. */
    protected function impedimentoParaDesactivar(Model $registro): ?string
    {
        return null;
    }

    protected function impedimentoParaEliminar(Model $registro): ?string
    {
        return $registro->tieneDependientes()
            ? 'No se puede eliminar porque tiene datos asociados; desactívalo para conservar el historial.'
            : null;
    }

    public function desactivar(int $id): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $registro = $this->modelo()::findOrFail($id);

        if ($motivo = $this->impedimentoParaDesactivar($registro)) {
            $this->addError('general', $motivo);

            return;
        }

        $registro->update(['activo' => false]);
    }

    public function reactivar(int $id): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $this->modelo()::findOrFail($id)->update(['activo' => true]);
    }

    public function eliminar(int $id): void
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $registro = $this->modelo()::findOrFail($id);

        if ($motivo = $this->impedimentoParaEliminar($registro)) {
            $this->addError('general', $motivo);

            return;
        }

        try {
            $registro->delete();
        } catch (QueryException) {
            $this->addError('general', 'No se puede eliminar porque tiene datos asociados; desactívalo para conservar el historial.');
        }
    }
}
