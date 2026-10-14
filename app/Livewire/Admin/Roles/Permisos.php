<?php

namespace App\Livewire\Admin\Roles;

use App\Autorizacion\ProteccionAdministracion;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Permisos extends Component
{
    use AuthorizesRequests;

    public Rol $rol;

    /** @var array<int, int|string> */
    public array $seleccionados = [];

    public function mount(Rol $rol): void
    {
        $this->authorize('roles.ver');

        $this->rol = $rol;
        $this->seleccionados = $rol->permisos()->pluck('permisos.id')->all();
    }

    public function guardar(ProteccionAdministracion $proteccion): void
    {
        $this->authorize('roles.asignar_permisos');

        $this->validate([
            'seleccionados' => 'array',
            'seleccionados.*' => 'exists:permisos,id',
        ]);

        $ids = array_values(array_unique(array_map('intval', $this->seleccionados)));

        $guardado = $proteccion->protegido(function () use ($proteccion, $ids) {
            if (! $proteccion->puedeSincronizarPermisos($this->rol, $ids)) {
                return false;
            }

            $this->rol->permisos()->sync($ids);

            return true;
        });

        if (! $guardado) {
            $this->addError('seleccionados', 'No se puede guardar: el sistema quedaría sin cuentas administradoras.');

            return;
        }

        $this->seleccionados = $ids;
        session()->flash('exito', 'Permisos del rol actualizados.');
    }

    public function render(): View
    {
        return view('livewire.admin.roles.permisos', ['permisos' => Permiso::orderBy('identificador')->get()]);
    }
}
