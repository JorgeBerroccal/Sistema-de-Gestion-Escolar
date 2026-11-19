<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Nómina de {{ $seccion->grado->nivel->nombre }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }} ({{ $seccion->anio->nombre }})</h1>
        <a href="{{ route('matricula.nominas.index') }}" class="underline">Volver</a>
    </div>

    @if ($mensaje !== '')
        <x-alerta tipo="exito" class="mb-4">{{ $mensaje }}</x-alerta>
    @endif
    @error('general')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    <div class="mb-3 flex flex-wrap gap-3">
        <div>
            <label for="busqueda" class="mr-2 text-sm font-medium">Buscar</label>
            <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Nombre, apellido o documento" class="rounded border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label for="filtroEstado" class="mr-2 text-sm font-medium">Estado</label>
            <select id="filtroEstado" wire:model.live="filtroEstado" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                @foreach ($estados as $estado) <option value="{{ $estado->value }}">{{ $estado->etiqueta() }}</option> @endforeach
            </select>
        </div>
    </div>

    <x-tabla>
        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
            <tr><th class="p-2">Apellidos y nombres</th><th class="p-2">Documento</th><th class="p-2">Estado</th><th class="p-2">Matrícula</th>@if ($puedeVerFicha)<th class="p-2">Acciones</th>@endif</tr>
        </thead>
        <tbody>
            @forelse ($matriculas as $matricula)
                <tr wire:key="matricula-{{ $matricula->id }}" class="border-t">
                    <td class="p-2">{{ $matricula->alumno->nombre_completo }}</td>
                    <td class="p-2">{{ $matricula->alumno->tipoDocumento->codigo }} {{ $matricula->alumno->numero_documento }}</td>
                    <td class="p-2">{{ $matricula->estado->etiqueta() }}</td>
                    <td class="p-2">{{ $matricula->fecha_matricula->format('d/m/Y') }}</td>
                    @if ($puedeVerFicha)
                        <td class="p-2"><a href="{{ route('matricula.alumnos.show', $matricula->alumno) }}" class="underline">Ver ficha</a></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-center text-gray-500">No hay alumnos que coincidan.</td></tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div class="mt-4">{{ $matriculas->links() }}</div>

    @if ($admiteMatricula)
        <section class="mt-8 rounded bg-white p-6 shadow">
            <h2 class="mb-3 text-lg font-semibold">Matricular alumnos en esta sección</h2>
            <p class="mb-3 text-sm text-gray-600">Marca uno o varios alumnos. Si alguno no se puede matricular, no se matricula ninguno.</p>
            <div class="mb-3">
                <label for="busquedaElegibles" class="mr-2 text-sm font-medium">Buscar alumno</label>
                <input id="busquedaElegibles" type="search" wire:model.live.debounce.300ms="busquedaElegibles" placeholder="Nombre, apellido o documento" class="rounded border border-gray-300 px-3 py-2">
            </div>
            <ul class="mb-3 max-h-64 overflow-auto">
                @forelse ($elegibles as $alumno)
                    <li wire:key="elegible-{{ $alumno->id }}">
                        <label class="flex items-center gap-2 py-1">
                            <input type="checkbox" wire:model="seleccionados" value="{{ $alumno->id }}">
                            {{ $alumno->nombre_completo }} · {{ $alumno->tipoDocumento->codigo }} {{ $alumno->numero_documento }}
                        </label>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No hay alumnos activos sin matrícula en el año vigente.</li>
                @endforelse
            </ul>
            <x-boton type="button" wire:click="matricularSeleccionados">Matricular seleccionados</x-boton>
        </section>
    @endif
</div>
