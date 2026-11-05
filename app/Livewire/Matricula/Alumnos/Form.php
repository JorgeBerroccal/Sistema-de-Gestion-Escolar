<?php

namespace App\Livewire\Matricula\Alumnos;

use App\Matricula\AlumnoService;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\Sexo;
use App\Models\Alumno;
use App\Models\TipoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?Alumno $alumno = null;

    public string $nombres = '';

    public string $apellidos = '';

    public string $tipo_documento_id = '';

    public string $numero_documento = '';

    public string $fecha_nacimiento = '';

    public string $sexo = '';

    public function mount(?Alumno $alumno = null): void
    {
        $this->authorize('matricula.gestionar');

        if ($alumno?->exists) {
            $this->alumno = $alumno;
            $this->nombres = $alumno->nombres;
            $this->apellidos = $alumno->apellidos;
            $this->tipo_documento_id = (string) $alumno->tipo_documento_id;
            $this->numero_documento = $alumno->numero_documento;
            $this->fecha_nacimiento = $alumno->fecha_nacimiento->format('Y-m-d');
            $this->sexo = $alumno->sexo->value;
        }
    }

    public function guardar(AlumnoService $servicio)
    {
        $this->authorize('matricula.gestionar');
        $this->resetErrorBag();

        $datos = [
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'tipo_documento_id' => $this->tipo_documento_id,
            'numero_documento' => $this->numero_documento,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'sexo' => $this->sexo,
        ];

        try {
            $alumno = $this->alumno?->exists ? $servicio->actualizar($this->alumno, $datos) : $servicio->crear($datos);
        } catch (MatriculaIncumplida $e) {
            $this->addError('general', $e->getMessage());

            return null;
        }

        session()->flash('estado', 'Ficha del alumno guardada.');

        return $this->redirectRoute('matricula.alumnos.show', $alumno, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.matricula.alumnos.form', [
            'tipos' => TipoDocumento::query()->where('activo', true)->orderBy('id')->get(),
            'sexos' => Sexo::cases(),
        ]);
    }
}
