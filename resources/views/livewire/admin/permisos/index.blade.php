<div class="space-y-6">
    <h1 class="text-2xl font-semibold">Permisos</h1>

    @can('permisos.gestionar')
        <form wire:submit="crear" class="space-y-3 rounded bg-white p-4 shadow">
            <h2 class="font-semibold">Crear permiso</h2>
            <div>
                <label for="identificador" class="block text-sm font-medium">Identificador (ej. notas.ver)</label>
                <input id="identificador" type="text" wire:model="identificador" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('identificador') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="nombre" class="block text-sm font-medium">Nombre</label>
                <input id="nombre" type="text" wire:model="nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="descripcion" class="block text-sm font-medium">Descripción</label>
                <input id="descripcion" type="text" wire:model="descripcion" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('descripcion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Crear permiso</button>
        </form>
    @endcan

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Identificador</th>
                <th class="p-2">Nombre</th>
                <th class="p-2">Descripción</th>
                @can('permisos.gestionar') <th class="p-2">Acciones</th> @endcan
            </tr>
        </thead>
        <tbody>
            @foreach ($permisos as $permiso)
                <tr wire:key="permiso-{{ $permiso->id }}" class="border-t align-top">
                    <td class="p-2 font-mono text-sm">{{ $permiso->identificador }}</td>
                    @can('permisos.gestionar')
                        <td class="p-2">
                            <input type="text" wire:model="edicion.{{ $permiso->id }}.nombre" aria-label="Nombre de {{ $permiso->identificador }}"
                                   class="w-full rounded border border-gray-300 px-2 py-1">
                            @error("edicion.{$permiso->id}.nombre") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </td>
                        <td class="p-2">
                            <input type="text" wire:model="edicion.{{ $permiso->id }}.descripcion" aria-label="Descripción de {{ $permiso->identificador }}"
                                   class="w-full rounded border border-gray-300 px-2 py-1">
                            @error("edicion.{$permiso->id}.descripcion") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </td>
                        <td class="p-2"><button type="button" wire:click="actualizar({{ $permiso->id }})" class="underline">Guardar</button></td>
                    @else
                        <td class="p-2">{{ $permiso->nombre }}</td>
                        <td class="p-2">{{ $permiso->descripcion }}</td>
                    @endcan
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
