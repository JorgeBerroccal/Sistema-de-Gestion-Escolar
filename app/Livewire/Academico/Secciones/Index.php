<?php

namespace App\Livewire\Academico\Secciones;

use App\Livewire\Academico\Concerns\CambiaEstado;
use App\Models\AnioEscolar;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use CambiaEstado;
    use WithPagination;

    #[Url]
    public string $filtroAnio = '';

    #[Url]
    public string $filtroNivel = '';

    #[Url]
    public string $filtroGrado = '';

    public ?int $editandoId = null;

    public string $grado_id = '';

    public string $anio_escolar_id = '';

    /** Un identificador, o varios separados por comas (A, B, C) al crear. */
    public string $identificador = '';

    protected function modelo(): string
    {
        return Seccion::class;
    }

    public function mount(): void
    {
        $this->authorize('academico.ver');
    }

    public function updatingFiltroAnio(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroNivel(): void
    {
        $this->resetPage();
        $this->filtroGrado = '';
    }

    public function updatingFiltroGrado(): void
    {
        $this->resetPage();
    }

    public function editar(int $id): void
    {
        $this->authorize('academico.gestionar');

        $seccion = Seccion::findOrFail($id);
        $this->editandoId = $seccion->id;
        $this->grado_id = (string) $seccion->grado_id;
        $this->anio_escolar_id = (string) $seccion->anio_escolar_id;
        $this->identificador = $seccion->identificador;
    }

    public function cancelar(): void
    {
        $this->reset('editandoId', 'grado_id', 'anio_escolar_id', 'identificador');
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->authorize('academico.gestionar');

        $reglasBase = [
            'grado_id' => ['required', Rule::exists('grados', 'id')->where('activo', true)],
            'anio_escolar_id' => ['required', Rule::exists('anios_escolares', 'id')->where('activo', true)],
        ];
        $mensajes = [
            'grado_id.required' => 'Elige el grado.',
            'grado_id.exists' => 'El grado elegido no existe o está inactivo.',
            'anio_escolar_id.required' => 'Elige el año escolar.',
            'anio_escolar_id.exists' => 'El año escolar elegido no existe o está inactivo.',
        ];

        $identificadores = $this->editandoId
            ? [mb_strtoupper(preg_replace('/\s+/u', ' ', trim($this->identificador)))]
            : collect(explode(',', $this->identificador))
                ->map(fn (string $i) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($i))))
                ->filter(fn (string $i) => $i !== '')->values()->all();

        if (count($identificadores) !== count(array_unique($identificadores))) {
            $this->fallar('Hay identificadores repetidos en la lista.');
        }

        $this->validate($reglasBase, $mensajes);
        $this->validateIdentificadores($identificadores);

        DB::transaction(function () use ($identificadores) {
            if ($this->editandoId) {
                Seccion::findOrFail($this->editandoId)->update([
                    'grado_id' => $this->grado_id,
                    'anio_escolar_id' => $this->anio_escolar_id,
                    'identificador' => $identificadores[0],
                ]);

                return;
            }

            foreach ($identificadores as $identificador) {
                Seccion::create([
                    'grado_id' => $this->grado_id,
                    'anio_escolar_id' => $this->anio_escolar_id,
                    'identificador' => $identificador,
                    'activo' => true,
                ]);
            }
        });

        $this->cancelar();
    }

    /** @param  array<int, string>  $identificadores */
    private function validateIdentificadores(array $identificadores): void
    {
        if ($identificadores === []) {
            $this->fallar('Indica al menos un identificador de sección.');
        }

        foreach ($identificadores as $identificador) {
            if (mb_strlen($identificador) > 10) {
                $this->fallar('Cada identificador admite como máximo 10 caracteres.');
            }

            $repetida = Seccion::query()
                ->where('grado_id', $this->grado_id)
                ->where('anio_escolar_id', $this->anio_escolar_id)
                ->where('identificador', $identificador)
                ->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))
                ->exists();

            if ($repetida) {
                $this->fallar("La sección {$identificador} ya existe en ese grado y año.");
            }
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['identificador' => $mensaje]);
    }

    public function render(): View
    {
        return view('livewire.academico.secciones.index', [
            'anios' => AnioEscolar::query()->orderByDesc('fecha_inicio')->get(),
            'niveles' => Nivel::query()->orderBy('orden')->get(),
            'gradosFiltro' => Grado::query()->with('nivel')
                ->when($this->filtroNivel !== '', fn ($q) => $q->where('nivel_id', $this->filtroNivel))
                ->orderBy('nivel_id')->orderBy('orden')->get(),
            'gradosActivos' => Grado::query()->with('nivel')->where('activo', true)->orderBy('nivel_id')->orderBy('orden')->get(),
            'secciones' => Seccion::query()->with(['grado.nivel', 'anio'])->withExists('asignaciones')
                ->when($this->filtroAnio !== '', fn ($q) => $q->where('anio_escolar_id', $this->filtroAnio))
                ->when($this->filtroGrado !== '', fn ($q) => $q->where('grado_id', $this->filtroGrado))
                ->when($this->filtroNivel !== '', fn ($q) => $q->whereHas('grado', fn ($g) => $g->where('nivel_id', $this->filtroNivel)))
                ->orderBy('anio_escolar_id')->orderBy('grado_id')->orderBy('identificador')->paginate(15),
        ]);
    }
}
