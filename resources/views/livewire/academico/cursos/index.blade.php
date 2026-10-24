<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Cursos</h1>
        @can('academico.gestionar')
            <a href="{{ route('academico.cursos.create') }}" class="rounded bg-gray-800 px-3 py-2 text-white">Crear curso</a>
        @endcan
    </div>

    <div class="mb-3">
        <label for="filtroNivel" class="mr-2 text-sm font-medium">Nivel</label>
        <select id="filtroNivel" wire:model.live="filtroNivel" class="rounded border border-gray-300 px-3 py-2">
            <option value="">Todos</option>
            @foreach ($niveles as $nivel) <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option> @endforeach
        </select>
    </div>

    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Nivel</th><th class="p-2">Curso</th><th class="p-2">Grados</th><th class="p-2">Estado</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($cursos as $curso)
                <tr wire:key="curso-{{ $curso->id }}" class="border-t">
                    <td class="p-2">{{ $curso->nivel->nombre }}</td>
                    <td class="p-2">{{ $curso->nombre }}</td>
                    <td class="p-2">{{ $curso->grados->pluck('nombre')->join(', ') }}</td>
                    <td class="p-2">{{ $curso->activo ? 'Activo' : 'Inactivo' }}</td>
                    <td class="flex gap-3 p-2">
                        @can('academico.gestionar')
                            <a href="{{ route('academico.cursos.edit', $curso) }}" class="underline">Editar</a>
                        @endcan
                        @can('academico.gestionar')
                            @if ($curso->activo)
                                <button type="button" wire:click="desactivar({{ $curso->id }})" class="underline">Desactivar</button>
                            @else
                                <button type="button" wire:click="reactivar({{ $curso->id }})" class="underline">Reactivar</button>
                            @endif
                            @unless ($curso->tieneDependientes())
                                <button type="button" wire:click="eliminar({{ $curso->id }})" wire:confirm="¿Eliminar definitivamente?" class="text-red-700 underline">Eliminar</button>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">Aún no hay cursos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $cursos->links() }}</div>
</div>
