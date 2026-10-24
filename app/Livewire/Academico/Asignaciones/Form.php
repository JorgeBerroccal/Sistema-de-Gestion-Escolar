<?php

namespace App\Livewire\Academico\Asignaciones;

use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Asigna uno o varios cursos de una sección del año vigente a un docente, o reasigna una asignación. */
class Form extends Component
{
    use AuthorizesRequests;

    #[Url]
    public ?int $reasignando = null;

    public string $seccion_id = '';

    /** @var array<int, int|string> */
    public array $cursos = [];

    public string $busquedaDocente = '';

    public string $docente_id = '';

    public function mount(): void
    {
        $this->authorize('asignaciones.gestionar');
    }

    public function updatedSeccionId(): void
    {
        $this->cursos = [];
    }

    public function guardar(AsignacionService $servicio)
    {
        $this->authorize('asignaciones.gestionar');
        $this->resetErrorBag('general');

        $reglas = ['docente_id' => 'required|integer|exists:usuarios,id'];
        $mensajes = ['docente_id.required' => 'Elige un docente.', 'docente_id.exists' => 'El docente elegido no existe.'];

        if (! $this->reasignando) {
            $reglas += ['seccion_id' => 'required|integer|exists:secciones,id', 'cursos' => 'required|array|min:1', 'cursos.*' => 'integer'];
            $mensajes += ['seccion_id.required' => 'Elige la sección.', 'cursos.required' => 'Marca al menos un curso.', 'cursos.min' => 'Marca al menos un curso.'];
        }

        $this->validate($reglas, $mensajes);
        $docente = Usuario::findOrFail($this->docente_id);

        try {
            if ($this->reasignando) {
                $servicio->reasignar(AsignacionDocente::findOrFail($this->reasignando), $docente);
            } else {
                $servicio->asignarVarios(Seccion::findOrFail($this->seccion_id), $this->cursos, $docente);
            }
        } catch (ReglaAcademicaIncumplida $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        return $this->redirectRoute('academico.asignaciones.index');
    }

    public function render(AsignacionService $servicio): View
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $seccion = $this->seccion_id === '' ? null : Seccion::query()->with('grado')->find($this->seccion_id);

        return view('livewire.academico.asignaciones.form', [
            'vigente' => $vigente,
            'asignacion' => $this->reasignando
                ? AsignacionDocente::query()->with(['curso', 'seccion.grado', 'usuario'])->find($this->reasignando)
                : null,
            'secciones' => $vigente === null ? collect() : Seccion::query()->with('grado.nivel')
                ->where('anio_escolar_id', $vigente->id)->where('activo', true)
                ->whereHas('grado', fn ($g) => $g->where('activo', true))
                ->orderBy('grado_id')->orderBy('identificador')->get(),
            'cursosDisponibles' => $seccion === null ? collect() : $seccion->grado->cursos()->where('cursos.activo', true)
                ->whereNotIn('cursos.id', AsignacionDocente::query()->where('seccion_id', $seccion->id)->select('curso_id'))
                ->orderBy('cursos.nombre')->get(),
            'docentes' => $servicio->docentesElegibles($this->busquedaDocente)->limit(10)->get(),
        ]);
    }
}
