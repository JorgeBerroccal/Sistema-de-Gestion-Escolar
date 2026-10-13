<?php

namespace App\Livewire\Admin\Usuarios;

use App\Autorizacion\ProteccionAdministracion;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $q = '';

    public function mount(): void
    {
        $this->authorize('usuarios.ver');
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function desactivar(int $id, ProteccionAdministracion $proteccion): void
    {
        $this->authorize('usuarios.desactivar');
        $this->resetErrorBag('general');

        $usuario = Usuario::findOrFail($id);

        if ($usuario->id === auth()->id()) {
            $this->addError('general', 'No puedes desactivar tu propia cuenta.');

            return;
        }

        if (! $proteccion->puedeDesactivar($usuario)) {
            $this->addError('general', 'No se puede desactivar: el sistema quedaría sin cuentas administradoras.');

            return;
        }

        $usuario->update(['activo' => false]);
    }

    public function reactivar(int $id): void
    {
        $this->authorize('usuarios.desactivar');
        $this->resetErrorBag('general');

        Usuario::findOrFail($id)->update(['activo' => true]);
    }

    public function render(): View
    {
        $texto = trim($this->q);
        $patron = addcslashes($texto, '\\%_').'%';

        $usuarios = Usuario::query()
            ->with('rol')
            ->when($texto !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nombres', 'like', $patron)
                ->orWhere('apellidos', 'like', $patron)
                ->orWhere('username', 'like', $patron)
                ->orWhere('email', 'like', $patron)))
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('id')
            ->paginate(15);

        return view('livewire.admin.usuarios.index', ['usuarios' => $usuarios]);
    }
}
