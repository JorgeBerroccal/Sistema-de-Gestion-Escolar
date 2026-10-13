<?php

namespace App\Livewire\Admin\Usuarios;

use App\Autorizacion\ProteccionAdministracion;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?Usuario $usuario = null;

    public string $nombres = '';

    public string $apellidos = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $rol_id = null;

    public function mount(?Usuario $usuario = null): void
    {
        $this->authorize($usuario?->exists ? 'usuarios.editar' : 'usuarios.crear');

        if ($usuario?->exists) {
            $this->usuario = $usuario;
            $this->fill($usuario->only(['nombres', 'apellidos', 'username', 'rol_id']));
            $this->email = $usuario->email ?? '';
        }
    }

    public function guardar(ProteccionAdministracion $proteccion)
    {
        $editando = (bool) $this->usuario?->exists;
        $this->authorize($editando ? 'usuarios.editar' : 'usuarios.crear');

        $this->username = mb_strtolower(trim($this->username));
        $this->email = mb_strtolower(trim($this->email));
        $id = $this->usuario?->id;

        $reglas = [
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'username' => ['required', 'regex:'.Usuario::REGEX_USERNAME, "unique:usuarios,username,{$id}"],
            'email' => ['nullable', 'email', 'max:150', "unique:usuarios,email,{$id}"],
            'rol_id' => 'required|exists:roles,id',
        ];
        if (! $editando) {
            $reglas['password'] = 'required|string|min:8|confirmed';
        }

        $datos = $this->validate($reglas);

        if ($editando) {
            if ((int) $datos['rol_id'] !== (int) $this->usuario->rol_id
                && ! $proteccion->puedeCambiarRol($this->usuario, Rol::findOrFail($datos['rol_id']))) {
                $this->addError('rol_id', 'No se puede cambiar el rol: el sistema quedaría sin cuentas administradoras.');

                return;
            }

            $this->usuario->update($datos);
        } else {
            Usuario::create($datos + ['activo' => true]);
        }

        return $this->redirectRoute('admin.usuarios.index');
    }

    public function restablecerPassword(): void
    {
        $this->authorize('usuarios.editar');

        if (! $this->usuario?->exists) {
            abort(404);
        }

        $datos = $this->validate(['password' => 'required|string|min:8|confirmed']);

        $this->usuario->update(['password' => $datos['password']]);
        $this->reset('password', 'password_confirmation');
        session()->flash('exito', 'Contraseña restablecida.');
    }

    public function render(): View
    {
        return view('livewire.admin.usuarios.form', ['roles' => Rol::orderBy('nombre')->get()]);
    }
}
