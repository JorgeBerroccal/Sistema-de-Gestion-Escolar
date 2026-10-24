<div>
    <h1 class="mb-4 text-2xl font-semibold">Secciones</h1>

    @can('academico.gestionar')
        <form wire:submit="guardar" class="mb-6 flex max-w-4xl flex-wrap items-start gap-3 rounded bg-white p-4 shadow">
            <div class="w-56">
                <label for="anio_escolar_id" class="block text-sm font-medium">Año escolar</label>
                <select id="anio_escolar_id" wire:model="anio_escolar_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige un año</option>
                    @foreach ($anios->where('activo', true) as $anio)
                        <option value="{{ $anio->id }}">{{ $anio->nombre }}</option>
                    @endforeach
                </select>
                @error('anio_escolar_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="w-56">
                <label for="grado_id" class="block text-sm font-medium">Grado</label>
                <select id="grado_id" wire:model="grado_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige un grado</option>
                    @foreach ($gradosActivos as $grado)
                        <option value="{{ $grado->id }}">{{ $grado->nivel->nombre }} — {{ $grado->nombre }}</option>
                    @endforeach
                </select>
                @error('grado_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grow">
                <label for="identificador" class="block text-sm font-medium">{{ $editandoId ? 'Identificador' : 'Identificadores (separados por comas: A, B, C)' }}</label>
                <input id="identificador" type="text" wire:model="identificador" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('identificador') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 pt-6">
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">{{ $editandoId ? 'Guardar cambios' : 'Crear secciones' }}</button>
                @if ($editandoId)
                    <button type="button" wire:click="cancelar" class="px-4 py-2 underline">Cancelar</button>
                @endif
            </div>
        </form>
    @endcan

    <div class="mb-3 flex flex-wrap gap-3">
        <div>
            <label for="filtroAnio" class="mr-2 text-sm font-medium">Año</label>
            <select id="filtroAnio" wire:model.live="filtroAnio" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($anios as $anio) <option value="{{ $anio->id }}">{{ $anio->nombre }}</option> @endforeach
            </select>
        </div>
        <div>
            <label for="filtroNivel" class="mr-2 text-sm font-medium">Nivel</label>
            <select id="filtroNivel" wire:model.live="filtroNivel" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($niveles as $nivel) <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option> @endforeach
            </select>
        </div>
        <div>
            <label for="filtroGrado" class="mr-2 text-sm font-medium">Grado</label>
            <select id="filtroGrado" wire:model.live="filtroGrado" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($gradosFiltro as $grado) <option value="{{ $grado->id }}">{{ $grado->nivel->nombre }} — {{ $grado->nombre }}</option> @endforeach
            </select>
        </div>
    </div>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Año</th><th class="p-2">Nivel</th><th class="p-2">Grado</th><th class="p-2">Sección</th><th class="p-2">Estado</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($secciones as $seccion)
                <tr wire:key="seccion-{{ $seccion->id }}" class="border-t">
                    <td class="p-2">{{ $seccion->anio->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nivel->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nombre }}</td>
                    <td class="p-2">{{ $seccion->identificador }}</td>
                    <td class="p-2">{{ $seccion->activo ? 'Activa' : 'Inactiva' }}</td>
                    <td class="p-2">
                        @can('academico.gestionar')
                            <button type="button" wire:click="editar({{ $seccion->id }})" class="underline">Editar</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">Aún no hay secciones registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $secciones->links() }}</div>
</div>
