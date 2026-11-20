<div>
    <h1 class="mb-1 text-2xl font-semibold">Permisos del rol: {{ $rol->nombre }}</h1>
    <p class="mb-4"><a href="{{ route('admin.roles.index') }}" class="underline">Volver a roles</a></p>

    @if (session('exito'))
        <x-alerta tipo="exito" class="mb-4">{{ session('exito') }}</x-alerta>
    @endif
    @error('seleccionados')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror
    @error('seleccionados.*')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    <form wire:submit="guardar" class="space-y-4 rounded bg-white p-6 shadow">
        <ul class="space-y-2">
            @foreach ($permisos as $permiso)
                <li wire:key="permiso-{{ $permiso->id }}">
                    <label class="flex items-start gap-2">
                        <input type="checkbox" value="{{ $permiso->id }}" wire:model="seleccionados" class="mt-1"
                               @cannot('roles.asignar_permisos') disabled @endcannot>
                        <span><span class="font-mono text-sm">{{ $permiso->identificador }}</span> — {{ $permiso->nombre }}
                            @if ($permiso->descripcion)<span class="block text-sm text-gray-500">{{ $permiso->descripcion }}</span>@endif
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>

        @can('roles.asignar_permisos')
            <x-boton type="submit">Guardar permisos</x-boton>
        @endcan
    </form>
</div>
