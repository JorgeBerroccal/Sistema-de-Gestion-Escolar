<?php

namespace App\Livewire\Admin\Permisos;

use App\Models\Permiso;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public string $identificador = '';

    public string $nombre = '';

    public string $descripcion = '';

    /** @var array<int, array{nombre: string, descripcion: string}> */
    public array $edicion = [];

    public function mount(): void
    {
        $this->authorize('roles.ver');
        $this->cargarEdicion();
    }

    public function crear(): void
    {
        $this->authorize('permisos.gestionar');

        $datos = $this->validate([
            'identificador' => ['required', 'max:100', 'regex:'.Permiso::REGEX_IDENTIFICADOR, 'unique:permisos,identificador'],
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);

        Permiso::create($datos);

        $this->reset('identificador', 'nombre', 'descripcion');
        $this->cargarEdicion();
    }

    public function actualizar(int $id): void
    {
        $this->authorize('permisos.gestionar');

        $permiso = Permiso::findOrFail($id);

        $datos = $this->validate([
            "edicion.{$id}.nombre" => 'required|string|max:100',
            "edicion.{$id}.descripcion" => 'nullable|string|max:255',
        ])['edicion'][$id];

        // Solo nombre y descripción: el identificador es inmutable.
        $permiso->update(['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null]);
    }

    private function cargarEdicion(): void
    {
        $this->edicion = Permiso::orderBy('identificador')->get()
            ->mapWithKeys(fn (Permiso $p) => [$p->id => ['nombre' => $p->nombre, 'descripcion' => $p->descripcion ?? '']])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.admin.permisos.index', ['permisos' => Permiso::orderBy('identificador')->get()]);
    }
}
