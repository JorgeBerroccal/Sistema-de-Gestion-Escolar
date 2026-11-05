<?php

namespace App\Livewire\Matricula\MisAlumnos;

use App\Matricula\EstadoMatricula;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Los alumnos propios (Alumno) o los hijos (Padre de Familia): la consulta siempre pasa por `visiblePara()`. */
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        if (! Auth::user()->tienePermiso('matricula.ver_propia')) {
            throw new AccessDeniedHttpException('No tienes acceso a esta sección.');
        }
    }

    public function render(): View
    {
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();

        return view('livewire.matricula.mis-alumnos.index', [
            'vigente' => $vigente,
            'alumnos' => Alumno::query()->visiblePara(Auth::user())
                ->with(['tipoDocumento', 'matriculas' => fn ($q) => $q->where('anio_id', $vigente?->id ?? 0)->where('estado', EstadoMatricula::Matriculado->value)->with('seccion.grado.nivel')])
                ->orderBy('apellidos')->orderBy('nombres')->orderBy('alumnos.id')->paginate(15),
        ]);
    }
}
