<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Rol;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('roles.ver');
    }

    public function render(): View
    {
        return view('livewire.admin.roles.index', [
            'roles' => Rol::withCount(['usuarios', 'permisos'])->orderBy('nombre')->get(),
        ]);
    }
}
