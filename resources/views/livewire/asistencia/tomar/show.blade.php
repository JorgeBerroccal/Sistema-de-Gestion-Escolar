<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Asistencia de {{ $seccion->grado->nivel->nombre }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }} ({{ $seccion->anio->nombre }})</h1>
        <a href="{{ route('asistencia.tomar.index') }}" class="underline">Volver</a>
    </div>

    @if ($mensaje !== '')
        <p class="mb-4 rounded bg-green-100 p-3 text-sm text-green-800" role="status">{{ $mensaje }}</p>
    @endif
    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <div class="mb-3">
        <label for="fecha" class="mr-2 text-sm font-medium">Fecha</label>
        <input id="fecha" type="date" wire:model.live="fecha" max="{{ $hoy }}" class="rounded border border-gray-300 px-3 py-2">
    </div>

    @if ($aviso)
        <p class="mb-4 rounded bg-yellow-100 p-3 text-sm text-yellow-800" role="alert">{{ $aviso }}</p>
    @endif

    <form wire:submit="guardar">
        <table class="w-full bg-white text-left shadow">
            <thead class="bg-gray-200 text-sm">
                <tr><th class="p-2">Apellidos y nombres</th><th class="p-2">Estado</th><th class="p-2">Observación</th></tr>
            </thead>
            <tbody>
                @forelse ($alumnos as $alumno)
                    <tr wire:key="alumno-{{ $alumno->id }}" class="border-t">
                        <td class="p-2">{{ $alumno->nombre_completo }}</td>
                        <td class="p-2">
                            <select aria-label="Estado de {{ $alumno->nombre_completo }}" wire:model="filas.{{ $alumno->id }}.estado_id" @disabled($aviso) class="rounded border border-gray-300 px-2 py-1">
                                @foreach ($estados as $estado) <option value="{{ $estado->id }}">{{ $estado->nombre }}</option> @endforeach
                            </select>
                        </td>
                        <td class="p-2">
                            <input type="text" maxlength="255" aria-label="Observación de {{ $alumno->nombre_completo }}" wire:model="filas.{{ $alumno->id }}.observacion" @disabled($aviso) class="w-full rounded border border-gray-300 px-2 py-1">
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-4 text-center text-gray-500">La sección no tiene alumnos matriculados en esa fecha.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if (! $aviso && $alumnos->isNotEmpty())
            <button type="submit" class="mt-4 rounded bg-blue-600 px-4 py-2 text-white">Guardar asistencia</button>
        @endif
    </form>
</div>
