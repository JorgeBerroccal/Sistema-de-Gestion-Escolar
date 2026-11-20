<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Alumnos</h1>
        @can('matricula.gestionar')
            <x-boton href="{{ route('matricula.alumnos.create') }}">Registrar alumno</x-boton>
        @endcan
    </div>

    @error('general')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    <div class="mb-3 flex flex-wrap gap-3">
        <div>
            <label for="busqueda" class="mr-2 text-sm font-medium">Buscar</label>
            <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Nombre, apellido o documento" class="rounded border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label for="filtroActivo" class="mr-2 text-sm font-medium">Ficha</label>
            <select id="filtroActivo" wire:model.live="filtroActivo" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todas</option>
                <option value="1">Activas</option>
                <option value="0">Desactivadas</option>
            </select>
        </div>
        <div>
            <label for="filtroEstado" class="mr-2 text-sm font-medium">Matrícula {{ $vigente ? 'de '.$vigente->nombre : '' }}</label>
            <select id="filtroEstado" wire:model.live="filtroEstado" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todas</option>
                @foreach ($estados as $estado) <option value="{{ $estado->value }}">{{ $estado->etiqueta() }}</option> @endforeach
                <option value="sin_matricula">Sin matrícula</option>
            </select>
        </div>
    </div>

    <x-tabla>
        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
            <tr><th class="p-2">Apellidos y nombres</th><th class="p-2">Documento</th><th class="p-2">Nacimiento</th><th class="p-2">Estado</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($alumnos as $alumno)
                <tr wire:key="alumno-{{ $alumno->id }}" class="border-t">
                    <td class="p-2">{{ $alumno->nombre_completo }}</td>
                    <td class="p-2">{{ $alumno->tipoDocumento->codigo }} {{ $alumno->numero_documento }}</td>
                    <td class="p-2">{{ $alumno->fecha_nacimiento->format('d/m/Y') }}</td>
                    <td class="p-2">{{ $alumno->activo ? 'Activo' : 'Desactivado' }}</td>
                    <td class="flex gap-3 p-2">
                        <a href="{{ route('matricula.alumnos.show', $alumno) }}" class="underline">Ver ficha</a>
                        @can('matricula.gestionar')
                            <a href="{{ route('matricula.alumnos.edit', $alumno) }}" class="underline">Editar</a>
                            @if ($alumno->activo)
                                <button type="button" wire:click="desactivar({{ $alumno->id }})" class="underline">Desactivar</button>
                            @else
                                <button type="button" wire:click="reactivar({{ $alumno->id }})" class="underline">Reactivar</button>
                            @endif
                            @unless ($alumno->matriculas_exists)
                                <button type="button" wire:click="eliminar({{ $alumno->id }})" wire:confirm="¿Eliminar definitivamente?" class="text-red-700 underline">Eliminar</button>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No hay alumnos que coincidan.</td></tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div class="mt-4">{{ $alumnos->links() }}</div>
</div>
