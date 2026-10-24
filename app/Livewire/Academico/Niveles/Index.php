<?php

namespace App\Livewire\Academico\Niveles;

use App\Models\Nivel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $orden = '';

    public function mount(): void
    {
        $this->authorize('academico.ver');
    }

    public function editar(int $id): void
    {
        $this->authorize('academico.gestionar');

        $nivel = Nivel::findOrFail($id);
        $this->editandoId = $nivel->id;
        $this->nombre = $nivel->nombre;
        $this->orden = (string) $nivel->orden;
    }

    public function cancelar(): void
    {
        $this->reset('editandoId', 'nombre', 'orden');
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->authorize('academico.gestionar');

        $this->nombre = preg_replace('/\s+/u', ' ', trim($this->nombre));

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('niveles', 'nombre')->ignore($this->editandoId)],
            'orden' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('niveles', 'orden')->ignore($this->editandoId)],
        ]);

        if ($this->editandoId) {
            Nivel::findOrFail($this->editandoId)->update($datos);
        } else {
            Nivel::create($datos + ['activo' => true]);
        }

        $this->cancelar();
    }

    public function render(): View
    {
        return view('livewire.academico.niveles.index', [
            'niveles' => Nivel::query()->orderBy('orden')->orderBy('id')->paginate(15),
        ]);
    }
}
