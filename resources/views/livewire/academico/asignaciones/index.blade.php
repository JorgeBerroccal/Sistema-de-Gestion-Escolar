<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $veTodas ? 'Asignaciones' : 'Mis asignaciones' }}</h1>
        @can('asignaciones.gestionar')
            <a href="{{ route('academico.asignaciones.create') }}" class="rounded bg-gray-800 px-3 py-2 text-white">Asignar docente</a>
        @endcan
    </div>

    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <div class="mb-3 flex flex-wrap gap-3">
        <div>
            <label for="filtroAnio" class="mr-2 text-sm font-medium">Año</label>
            <select id="filtroAnio" wire:model.live="filtroAnio" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($anios as $anio) <option value="{{ $anio->id }}">{{ $anio->nombre }}</option> @endforeach
            </select>
        </div>
        @if ($filtroAnio !== '')
            <div>
                <label for="filtroSeccion" class="mr-2 text-sm font-medium">Sección</label>
                <select id="filtroSeccion" wire:model.live="filtroSeccion" class="rounded border border-gray-300 px-3 py-2">
                    <option value="">Todas</option>
                    @foreach ($secciones as $seccion) <option value="{{ $seccion->id }}">{{ $seccion->grado->nombre }} {{ $seccion->identificador }}</option> @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="filtroCurso" class="mr-2 text-sm font-medium">Curso</label>
            <select id="filtroCurso" wire:model.live="filtroCurso" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($cursos as $curso) <option value="{{ $curso->id }}">{{ $curso->nombre }}</option> @endforeach
            </select>
        </div>
        @if ($veTodas)
            <div>
                <label for="filtroDocente" class="mr-2 text-sm font-medium">Docente</label>
                <input id="filtroDocente" type="search" wire:model.live.debounce.300ms="filtroDocente" placeholder="Nombre o apellido" class="rounded border border-gray-300 px-3 py-2">
            </div>
        @endif
    </div>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Año</th><th class="p-2">Nivel</th><th class="p-2">Grado y sección</th>
                <th class="p-2">Curso</th><th class="p-2">Docente</th><th class="p-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($asignaciones as $asignacion)
                <tr wire:key="asignacion-{{ $asignacion->id }}" class="border-t">
                    <td class="p-2">{{ $asignacion->seccion->anio->nombre }}</td>
                    <td class="p-2">{{ $asignacion->seccion->grado->nivel->nombre }}</td>
                    <td class="p-2">{{ $asignacion->seccion->grado->nombre }} {{ $asignacion->seccion->identificador }}</td>
                    <td class="p-2">{{ $asignacion->curso->nombre }}</td>
                    <td class="p-2">
                        {{ $asignacion->usuario->nombre_completo }}
                        @if ($asignacion->pendiente_reasignar)
                            <span class="ml-1 rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Pendiente de reasignar</span>
                        @endif
                    </td>
                    <td class="flex gap-3 p-2">
                        @can('asignaciones.gestionar')
                            <a href="{{ route('academico.asignaciones.create', ['reasignando' => $asignacion->id]) }}" class="underline">Reasignar</a>
                            <button type="button" wire:click="quitar({{ $asignacion->id }})"
                                    wire:confirm="¿Quitar esta asignación?" class="text-red-700 underline">Quitar</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">No hay asignaciones para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $asignaciones->links() }}</div>
</div>
