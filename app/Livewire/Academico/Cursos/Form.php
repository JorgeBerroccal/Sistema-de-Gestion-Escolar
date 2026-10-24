<?php

namespace App\Livewire\Academico\Cursos;

use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?Curso $curso = null;

    public string $nivel_id = '';

    public string $nombre = '';

    /** @var array<int, int|string> ids de los grados (del mismo nivel) en que se dicta */
    public array $grados = [];

    public function mount(?Curso $curso = null): void
    {
        $this->authorize('academico.gestionar');

        if ($curso?->exists) {
            $this->curso = $curso;
            $this->nivel_id = (string) $curso->nivel_id;
            $this->nombre = $curso->nombre;
            $this->grados = $curso->grados()->pluck('grados.id')->map(fn ($id) => (string) $id)->all();
        }
    }

    public function updatedNivelId(): void
    {
        $this->grados = [];
    }

    public function guardar()
    {
        $this->authorize('academico.gestionar');

        $this->nombre = preg_replace('/\s+/u', ' ', trim($this->nombre));

        $datos = $this->validate([
            'nivel_id' => ['required', Rule::exists('niveles', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('cursos', 'nombre')->where('nivel_id', $this->nivel_id)->ignore($this->curso?->id)],
            'grados' => ['required', 'array', 'min:1'],
            'grados.*' => ['integer', Rule::exists('grados', 'id')->where('nivel_id', $this->nivel_id)],
        ], [
            'nivel_id.required' => 'Elige el nivel del curso.',
            'nivel_id.exists' => 'El nivel elegido no existe o está inactivo.',
            'grados.required' => 'Marca al menos un grado en que se dicta el curso.',
            'grados.min' => 'Marca al menos un grado en que se dicta el curso.',
            'grados.*.exists' => 'Solo se pueden elegir grados del nivel del curso.',
        ]);

        DB::transaction(function () use ($datos) {
            $curso = $this->curso ?? new Curso(['activo' => true]);
            $curso->fill(['nivel_id' => $datos['nivel_id'], 'nombre' => $datos['nombre']])->save();
            $curso->grados()->sync(array_map('intval', $datos['grados']));
        });

        return $this->redirectRoute('academico.cursos.index');
    }

    public function render(): View
    {
        return view('livewire.academico.cursos.form', [
            'niveles' => Nivel::query()->where('activo', true)->orderBy('orden')->get(),
            'gradosDelNivel' => $this->nivel_id === ''
                ? collect()
                : Grado::query()->where('nivel_id', $this->nivel_id)->orderBy('orden')->get(),
        ]);
    }
}
