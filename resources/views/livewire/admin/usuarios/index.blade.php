<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Usuarios</h1>
        @can('usuarios.crear')
            <a href="{{ route('admin.usuarios.create') }}" class="rounded bg-gray-800 px-3 py-2 text-white">Crear usuario</a>
        @endcan
    </div>

    <input type="search" wire:model.live.debounce.300ms="q" placeholder="Buscar por nombre, apellido, usuario o correo"
           aria-label="Buscar usuarios" class="mb-4 w-full rounded border border-gray-300 px-3 py-2">

    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Nombre</th>
                <th class="p-2">Usuario</th>
                <th class="p-2">Correo</th>
                <th class="p-2">Rol</th>
                <th class="p-2">Estado</th>
                <th class="p-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usuarios as $usuario)
                <tr wire:key="usuario-{{ $usuario->id }}" class="border-t">
                    <td class="p-2">{{ $usuario->nombre_completo }}</td>
                    <td class="p-2">{{ $usuario->username }}</td>
                    <td class="p-2">{{ $usuario->email }}</td>
                    <td class="p-2">{{ $usuario->rol->nombre }}</td>
                    <td class="p-2">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td>
                    <td class="flex gap-3 p-2">
                        @can('usuarios.editar')
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="underline">Editar</a>
                        @endcan
                        @can('usuarios.desactivar')
                            @if ($usuario->activo)
                                <button type="button" wire:click="desactivar({{ $usuario->id }})"
                                        wire:confirm="¿Desactivar esta cuenta?" class="text-red-700 underline">Desactivar</button>
                            @else
                                <button type="button" wire:click="reactivar({{ $usuario->id }})" class="text-green-700 underline">Reactivar</button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">No hay usuarios que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $usuarios->links() }}</div>
</div>
