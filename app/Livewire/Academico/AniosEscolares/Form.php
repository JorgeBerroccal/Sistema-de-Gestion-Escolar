<?php

namespace App\Livewire\Academico\AniosEscolares;

use App\Academico\AnioEscolarService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Academico\TipoPeriodo;
use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?AnioEscolar $anio = null;

    public string $nombre = '';

    public string $fecha_inicio = '';

    public string $fecha_fin = '';

    public string $tipo_periodo = 'bimestre';

    /** @var array<int, array{nombre: string, fecha_inicio: string, fecha_fin: string}> */
    public array $periodos = [];

    public function mount(?AnioEscolar $anio = null): void
    {
        $this->authorize('academico.gestionar');

        if ($anio?->exists) {
            $this->anio = $anio;
            $this->nombre = $anio->nombre;
            $this->fecha_inicio = $anio->fecha_inicio->format('Y-m-d');
            $this->fecha_fin = $anio->fecha_fin->format('Y-m-d');
            $this->tipo_periodo = $anio->tipo_periodo->value;
            $this->periodos = $anio->periodos->map(fn ($p) => [
                'nombre' => $p->nombre,
                'fecha_inicio' => $p->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $p->fecha_fin->format('Y-m-d'),
            ])->values()->all();
        }

        $this->ajustarPeriodos();
    }

    public function updatedTipoPeriodo(): void
    {
        $this->ajustarPeriodos();
    }

    /** Deja la cantidad de periodos del tipo elegido, proponiendo los nombres que falten. */
    private function ajustarPeriodos(): void
    {
        $tipo = TipoPeriodo::tryFrom($this->tipo_periodo) ?? TipoPeriodo::Bimestre;
        $sugeridos = $tipo->nombresSugeridos();
        $nuevos = [];

        foreach ($sugeridos as $orden => $nombreSugerido) {
            $actual = $this->periodos[$orden - 1] ?? null;
            $nuevos[] = [
                'nombre' => $actual === null || $this->esNombreSugerido($actual['nombre']) ? $nombreSugerido : $actual['nombre'],
                'fecha_inicio' => $actual['fecha_inicio'] ?? '',
                'fecha_fin' => $actual['fecha_fin'] ?? '',
            ];
        }

        $this->periodos = $nuevos;
    }

    private function esNombreSugerido(string $nombre): bool
    {
        foreach (TipoPeriodo::cases() as $tipo) {
            if (in_array($nombre, $tipo->nombresSugeridos(), true)) {
                return true;
            }
        }

        return false;
    }

    public function guardar(AnioEscolarService $servicio)
    {
        $this->authorize('academico.gestionar');
        $this->resetErrorBag('general');

        $this->nombre = preg_replace('/\s+/u', ' ', trim($this->nombre));

        $this->validate([
            'nombre' => ['required', 'string', 'max:30', Rule::unique('anios_escolares', 'nombre')->ignore($this->anio?->id)],
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d|after:fecha_inicio',
            'tipo_periodo' => ['required', Rule::enum(TipoPeriodo::class)],
            'periodos' => 'required|array',
            'periodos.*.nombre' => 'required|string|max:40',
            'periodos.*.fecha_inicio' => 'required|date_format:Y-m-d',
            'periodos.*.fecha_fin' => 'required|date_format:Y-m-d',
        ]);

        try {
            $servicio->guardar($this->anio, [
                'nombre' => $this->nombre,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'tipo_periodo' => $this->tipo_periodo,
            ], $this->periodos);
        } catch (ReglaAcademicaIncumplida $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        return $this->redirectRoute('academico.anios.index');
    }

    public function render(): View
    {
        return view('livewire.academico.anios-escolares.form', ['tipos' => TipoPeriodo::cases()]);
    }
}
