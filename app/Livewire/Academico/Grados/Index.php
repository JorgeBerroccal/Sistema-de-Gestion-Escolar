<?php

namespace App\Livewire\Academico\Grados;

use App\Models\Grado;
use App\Models\Nivel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $filtroNivel = '';

    public ?int $editandoId = null;

    public string $nivel_id = '';

    public string $nombre = '';

    public string $orden = '';

    public function mount(): void
    {
        $this->authorize('academico.ver');
    }

    public function updatingFiltroNivel(): void
    {
        $this->resetPage();
    }

    public function editar(int $id): void
    {
        $this->authorize('academico.gestionar');

        $grado = Grado::findOrFail($id);
        $this->editandoId = $grado->id;
        $this->nivel_id = (string) $grado->nivel_id;
        $this->nombre = $grado->nombre;
        $this->orden = (string) $grado->orden;
    }

    public function cancelar(): void
    {
        $this->reset('editandoId', 'nivel_id', 'nombre', 'orden');
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->authorize('academico.gestionar');

        $this->nombre = preg_replace('/\s+/u', ' ', trim($this->nombre));

        $datos = $this->validate([
            'nivel_id' => ['required', Rule::exists('niveles', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:40', Rule::unique('grados', 'nombre')->where('nivel_id', $this->nivel_id)->ignore($this->editandoId)],
            'orden' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('grados', 'orden')->where('nivel_id', $this->nivel_id)->ignore($this->editandoId)],
        ], ['nivel_id.required' => 'Elige el nivel del grado.', 'nivel_id.exists' => 'El nivel elegido no existe o está inactivo.']);

        if ($this->editandoId) {
            Grado::findOrFail($this->editandoId)->update($datos);
        } else {
            Grado::create($datos + ['activo' => true]);
        }

        $this->cancelar();
    }

    public function render(): View
    {
        return view('livewire.academico.grados.index', [
            'niveles' => Nivel::query()->orderBy('orden')->get(),
            'grados' => Grado::query()->with('nivel')
                ->when($this->filtroNivel !== '', fn ($q) => $q->where('nivel_id', $this->filtroNivel))
                ->orderBy('nivel_id')->orderBy('orden')->paginate(15),
        ]);
    }
}
