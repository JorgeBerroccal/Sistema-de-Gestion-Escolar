<?php

namespace App\Livewire\Asistencia\Tomar;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\AnioEscolar;
use App\Models\Seccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Secciones del año vigente donde el usuario puede tomar asistencia: todas (gestionar) o las que tiene asignadas. */
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('asistencia.gestionar') || $usuario->tienePermiso('asistencia.registrar_propia'))) {
            throw new AccessDeniedHttpException('No tienes acceso a la toma de asistencia.');
        }
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();

        $secciones = Seccion::query()->with(['grado.nivel', 'anio'])
            ->where('secciones.activo', true)
            ->where('secciones.anio_escolar_id', $vigente?->id ?? 0)
            ->when(! $usuario->tienePermiso('asistencia.gestionar'), fn ($q) => $q->whereIn('secciones.id', MatriculaRegla::seccionesDelDocente($usuario)->select('seccion_id')))
            ->join('grados', 'grados.id', '=', 'secciones.grado_id')
            ->orderBy('grados.nombre')->orderBy('secciones.identificador')->orderBy('secciones.id')
            ->select('secciones.*')
            ->paginate(15);

        return view('livewire.asistencia.tomar.index', ['secciones' => $secciones, 'vigente' => $vigente]);
    }
}
