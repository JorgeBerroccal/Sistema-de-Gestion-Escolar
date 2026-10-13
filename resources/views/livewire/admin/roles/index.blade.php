<div>
    <h1 class="mb-4 text-2xl font-semibold">Roles</h1>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Rol</th>
                <th class="p-2">Descripción</th>
                <th class="p-2">Usuarios</th>
                <th class="p-2">Permisos</th>
                <th class="p-2"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($roles as $rol)
                <tr wire:key="rol-{{ $rol->id }}" class="border-t">
                    <td class="p-2">{{ $rol->nombre }}</td>
                    <td class="p-2">{{ $rol->descripcion }}</td>
                    <td class="p-2">{{ $rol->usuarios_count }}</td>
                    <td class="p-2">{{ $rol->permisos_count }}</td>
                    <td class="p-2"><a href="{{ route('admin.roles.permisos', $rol) }}" class="underline">Permisos</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
