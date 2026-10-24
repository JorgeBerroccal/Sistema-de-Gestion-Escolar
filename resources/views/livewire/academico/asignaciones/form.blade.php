<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $reasignando ? 'Reasignar docente' : 'Asignar docente' }}</h1>

    <form wire:submit="guardar" class="max-w-2xl space-y-4 rounded bg-white p-6 shadow">
        @error('general')
            <p class="rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        @if ($vigente === null)
            <p class="rounded bg-yellow-100 p-3 text-sm text-yellow-800">No hay un año escolar vigente; márcalo en «Años escolares» antes de asignar docentes.</p>
        @elseif ($asignacion)
            <p class="text-sm">Curso <strong>{{ $asignacion->curso->nombre }}</strong> en {{ $asignacion->seccion->grado->nombre }} {{ $asignacion->seccion->identificador }};
                docente actual: {{ $asignacion->usuario->nombre_completo }}.</p>
        @else
            <p class="text-sm text-gray-600">Año escolar vigente: <strong>{{ $vigente->nombre }}</strong></p>

            <div>
                <label for="seccion_id" class="block text-sm font-medium">Sección</label>
                <select id="seccion_id" wire:model.live="seccion_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige una sección</option>
                    @foreach ($secciones as $seccion)
                        <option value="{{ $seccion->id }}">{{ $seccion->grado->nivel->nombre }} — {{ $seccion->grado->nombre }} {{ $seccion->identificador }}</option>
                    @endforeach
                </select>
                @error('seccion_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <fieldset>
                <legend class="block text-sm font-medium">Cursos sin docente en la sección</legend>
                @forelse ($cursosDisponibles as $curso)
                    <label class="mr-4 inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="cursos" value="{{ $curso->id }}"> {{ $curso->nombre }}
                    </label>
                @empty
                    <p class="text-sm text-gray-500">{{ $seccion_id === '' ? 'Elige una sección.' : 'No quedan cursos por asignar en esta sección.' }}</p>
                @endforelse
                @error('cursos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>
        @endif

        @if ($vigente !== null)
            <div>
                <label for="busquedaDocente" class="block text-sm font-medium">Docente</label>
                <input id="busquedaDocente" type="search" wire:model.live.debounce.300ms="busquedaDocente" placeholder="Buscar por nombre, apellido o usuario"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                <div class="mt-2 space-y-1">
                    @forelse ($docentes as $docente)
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:model="docente_id" value="{{ $docente->id }}"> {{ $docente->nombre_completo }} <span class="text-sm text-gray-500">({{ $docente->username }})</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No hay docentes que coincidan.</p>
                    @endforelse
                </div>
                @error('docente_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">{{ $reasignando ? 'Reasignar' : 'Asignar' }}</button>
                <a href="{{ route('academico.asignaciones.index') }}" class="px-4 py-2 underline">Cancelar</a>
            </div>
        @endif
    </form>
</div>
