<?php

namespace App\Livewire\Academico\Institucion;

use App\Models\EscalaCalificacion;
use App\Models\Institucion;
use App\Models\Nivel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public string $nombre = '';

    public string $codigo_modular = '';

    public string $ugel_dre = '';

    /** @var array<int, int|string> ids de los niveles que atiende */
    public array $niveles = [];

    public ?int $escala_calificacion_id = null;

    public function mount(): void
    {
        $this->authorize('academico.ver');

        $ficha = Institucion::actual();

        if ($ficha->exists) {
            $this->fill($ficha->only(['nombre', 'codigo_modular', 'ugel_dre', 'escala_calificacion_id']));
            $this->niveles = $ficha->niveles()->pluck('niveles.id')->all();
        }
    }

    public function guardar(): void
    {
        $this->authorize('academico.gestionar');

        $this->nombre = trim($this->nombre);
        $this->codigo_modular = trim($this->codigo_modular);
        $this->ugel_dre = trim($this->ugel_dre);

        $datos = $this->validate([
            'nombre' => 'required|string|max:150',
            'codigo_modular' => 'required|string|max:20',
            'ugel_dre' => 'required|string|max:150',
            'niveles' => 'required|array|min:1',
            'niveles.*' => ['integer', Rule::exists('niveles', 'id')->where('activo', true)],
            'escala_calificacion_id' => 'nullable|exists:escalas_calificacion,id',
        ], ['niveles.required' => 'Marca al menos un nivel que atiende la institución.', 'niveles.min' => 'Marca al menos un nivel que atiende la institución.']);

        DB::transaction(function () use ($datos) {
            $ficha = Institucion::actual();
            $ficha->fill(collect($datos)->only(['nombre', 'codigo_modular', 'ugel_dre', 'escala_calificacion_id'])->all())->save();
            $ficha->niveles()->sync(array_map('intval', $datos['niveles']));
        });

        session()->flash('exito', 'Datos de la institución guardados.');
    }

    public function render(): View
    {
        return view('livewire.academico.institucion.edit', [
            'catalogoNiveles' => Nivel::query()->where('activo', true)->orderBy('orden')->get(),
            'escalas' => EscalaCalificacion::query()->orderBy('id')->get(),
            'escalaActual' => $this->escala_calificacion_id ? EscalaCalificacion::find($this->escala_calificacion_id) : null,
        ]);
    }
}
