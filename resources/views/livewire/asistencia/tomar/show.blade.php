<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Asistencia de {{ $seccion->grado->nivel->nombre }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }} ({{ $seccion->anio->nombre }})</h1>
        <a href="{{ route('asistencia.tomar.index') }}" class="underline">Volver</a>
    </div>

    @if ($mensaje !== '')
        <x-alerta tipo="exito" class="mb-4">{{ $mensaje }}</x-alerta>
    @endif
    @error('general')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    <div class="mb-3">
        <label for="fecha" class="mr-2 text-sm font-medium">Fecha</label>
        <input id="fecha" type="date" wire:model.live="fecha" max="{{ $hoy }}" class="rounded border border-gray-300 px-3 py-2">
    </div>

    @if ($aviso)
        <x-alerta tipo="aviso" class="mb-4">{{ $aviso }}</x-alerta>
    @endif

    <form wire:submit="guardar">
        <x-tabla>
            <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                <tr><th class="p-2">Apellidos y nombres</th><th class="p-2">Estado</th><th class="p-2">Observación</th><th class="p-2">Justificación</th></tr>
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
                        <td class="p-2">
                            @php($registro = $registros->get($alumno->id))
                            @if ($registro && in_array($registro->estado_id, $justificables, true))
                                <button type="button" wire:click="iniciarJustificacion({{ $registro->id }})" class="underline">Justificar</button>
                            @elseif ($registro && in_array($registro->estado_id, $justificados, true))
                                <span class="mr-2 text-sm">{{ $registro->motivo_justificacion }}</span>
                                <button type="button" wire:click="revertir({{ $registro->id }})" class="underline">Revertir</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-center text-gray-500">La sección no tiene alumnos matriculados en esa fecha.</td></tr>
                @endforelse
            </tbody>
        </x-tabla>

        @if (! $aviso && $alumnos->isNotEmpty())
            <x-boton type="submit" class="mt-4">Guardar asistencia</x-boton>
        @endif
    </form>

    @if ($justificandoId)
        <form wire:submit="justificar" class="mt-4 rounded bg-white p-4 shadow">
            <label for="motivo" class="mr-2 text-sm font-medium">Motivo de la justificación</label>
            <input id="motivo" type="text" maxlength="255" wire:model="motivo" class="w-96 rounded border border-gray-300 px-3 py-2">
            <x-boton type="submit" class="ml-2">Justificar falta</x-boton>
            <button type="button" wire:click="cancelarJustificacion" class="ml-2 underline">Cancelar</button>
        </form>
    @endif
</div>
