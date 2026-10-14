@php($editando = (bool) $usuario?->exists)
<div class="space-y-8">
    <div>
        <h1 class="mb-4 text-2xl font-semibold">{{ $editando ? 'Editar usuario' : 'Crear usuario' }}</h1>

        <form wire:submit="guardar" class="max-w-xl space-y-4 rounded bg-white p-6 shadow">
            <div>
                <label for="nombres" class="block text-sm font-medium">Nombres</label>
                <input id="nombres" type="text" wire:model.blur="nombres" @unless ($editando) autofocus @endunless
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('nombres') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="apellidos" class="block text-sm font-medium">Apellidos</label>
                <input id="apellidos" type="text" wire:model.blur="apellidos"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('apellidos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="username" class="block text-sm font-medium">Nombre de usuario</label>
                <input id="username" type="text" wire:model.blur="username"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @unless ($editando)
                <div>
                    <label for="password" class="block text-sm font-medium">Contraseña</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password"
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">Confirmar contraseña</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </div>
            @endunless

            <div>
                <label for="rol_id" class="block text-sm font-medium">Rol</label>
                <select id="rol_id" wire:model="rol_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Selecciona un rol</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                    @endforeach
                </select>
                @error('rol_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Correo (opcional)</label>
                <input id="email" type="email" wire:model="email"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Guardar</button>
                <a href="{{ route('admin.usuarios.index') }}" class="px-4 py-2 underline">Cancelar</a>
            </div>
        </form>
    </div>

    @if ($editando)
        <form wire:submit="restablecerPassword" class="max-w-xl space-y-4 rounded bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Restablecer contraseña</h2>
            @if (session('exito'))
                <p class="rounded bg-green-100 p-3 text-sm text-green-700" role="status">{{ session('exito') }}</p>
            @endif
            <div>
                <label for="nueva_password" class="block text-sm font-medium">Nueva contraseña</label>
                <input id="nueva_password" type="password" wire:model="password" autocomplete="new-password"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="nueva_password_confirmation" class="block text-sm font-medium">Confirmar nueva contraseña</label>
                <input id="nueva_password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Restablecer contraseña</button>
        </form>
    @endif
</div>
