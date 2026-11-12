<div>
    <h1 class="mb-4 text-2xl font-semibold">Mi asistencia</h1>

    @if ($alumnos->isEmpty())
        <p class="rounded bg-white p-4 text-center text-gray-500 shadow">No tienes alumnos vinculados.</p>
    @else
        <div class="mb-4 flex flex-wrap gap-4">
            <div>
                <label for="elegir-alumno" class="mr-2 text-sm font-medium">Alumno</label>
                <select id="elegir-alumno" wire:model.live="alumnoId" class="rounded border border-gray-300 px-3 py-2">
                    @foreach ($alumnos as $a) <option value="{{ $a->id }}" @selected($elegido?->id === $a->id)>{{ $a->nombre_completo }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="mes" class="mr-2 text-sm font-medium">Mes</label>
                <input id="mes" type="month" wire:model.live="mes" class="rounded border border-gray-300 px-3 py-2">
            </div>
        </div>

        <section class="mb-6 rounded bg-white p-4 shadow" aria-labelledby="resumen">
            <h2 id="resumen" class="mb-2 text-lg font-semibold">Resumen del año {{ $vigente?->nombre }}</h2>
            @if ($resumen['registrados'] === 0)
                <p class="text-gray-500">Sin registros de asistencia todavía.</p>
            @else
                <ul class="flex flex-wrap gap-6 text-sm">
                    @foreach ($resumen['por_estado'] as $fila) <li>{{ $fila->nombre }}: <strong>{{ $fila->total }}</strong></li> @endforeach
                    <li>Asistencia: <strong>{{ $resumen['porcentaje'] === null ? 'sin datos' : \App\Support\Formato::recortado($resumen['porcentaje']).' %' }}</strong></li>
                </ul>
            @endif
        </section>

        <table class="w-full bg-white text-left shadow">
            <thead class="bg-gray-200 text-sm">
                <tr><th class="p-2">Fecha</th><th class="p-2">Estado</th><th class="p-2">Observación</th><th class="p-2">Motivo de justificación</th></tr>
            </thead>
            <tbody>
                @forelse ($registros as $registro)
                    <tr wire:key="registro-{{ $registro->id }}" class="border-t">
                        <td class="p-2">{{ $registro->fecha->format('d/m/Y') }}</td>
                        <td class="p-2">{{ $registro->estado->nombre }}</td>
                        <td class="p-2">{{ $registro->observacion }}</td>
                        <td class="p-2">{{ $registro->motivo_justificacion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-center text-gray-500">Sin registros en este mes.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
