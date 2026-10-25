<?php

namespace App\Livewire\Academico\Asignaciones;

use App\Models\AsignacionDocente;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Show extends Component
{
    public AsignacionDocente $asignacion;

    public function mount(AsignacionDocente $asignacion): void
    {
        $usuario = Auth::user();

        if (! ($usuario->tienePermiso('asignaciones.ver_todas') || $usuario->tienePermiso('asignaciones.ver_propias'))) {
            throw new AccessDeniedHttpException('No tienes acceso a las asignaciones.');
        }

        // La pertenencia se comprueba antes de cargar relaciones: una asignación ajena no revela datos.
        $asignacion->autorizarPertenencia($usuario);

        $this->asignacion = $asignacion->load(['usuario.rol.permisos', 'curso', 'seccion.grado.nivel', 'seccion.anio']);
    }

    public function render(): View
    {
        return view('livewire.academico.asignaciones.show');
    }
}
