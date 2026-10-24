<div>
    <h1 class="mb-4 text-2xl font-semibold">Grados</h1>

    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    @can('academico.gestionar')
        <form wire:submit="guardar" class="mb-6 flex max-w-3xl flex-wrap items-start gap-3 rounded bg-white p-4 shadow">
            <div class="w-56">
                <label for="nivel_id" class="block text-sm font-medium">Nivel</label>
                <select id="nivel_id" wire:model="nivel_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige un nivel</option>
                    @foreach ($niveles->where('activo', true) as $nivel)
                        <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option>
                    @endforeach
                </select>
                @error('nivel_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grow">
                <label for="nombre" class="block text-sm font-medium">Nombre</label>
                <input id="nombre" type="text" wire:model="nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="w-28">
                <label for="orden" class="block text-sm font-medium">Orden</label>
                <input id="orden" type="number" min="1" wire:model="orden" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('orden') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 pt-6">
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">{{ $editandoId ? 'Guardar cambios' : 'Crear grado' }}</button>
                @if ($editandoId)
                    <button type="button" wire:click="cancelar" class="px-4 py-2 underline">Cancelar</button>
                @endif
            </div>
        </form>
    @endcan

    <div class="mb-3">
        <label for="filtroNivel" class="mr-2 text-sm font-medium">Nivel</label>
        <select id="filtroNivel" wire:model.live="filtroNivel" class="rounded border border-gray-300 px-3 py-2">
            <option value="">Todos</option>
            @foreach ($niveles as $nivel)
                <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option>
            @endforeach
        </select>
    </div>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Nivel</th><th class="p-2">Orden</th><th class="p-2">Nombre</th><th class="p-2">Estado</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($grados as $grado)
                <tr wire:key="grado-{{ $grado->id }}" class="border-t">
                    <td class="p-2">{{ $grado->nivel->nombre }}</td>
                    <td class="p-2">{{ $grado->orden }}</td>
                    <td class="p-2">{{ $grado->nombre }}</td>
                    <td class="p-2">{{ $grado->activo ? 'Activo' : 'Inactivo' }}</td>
                    <td class="p-2">
                        @can('academico.gestionar')
                            <button type="button" wire:click="editar({{ $grado->id }})" class="underline">Editar</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">Aún no hay grados registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $grados->links() }}</div>
</div>
