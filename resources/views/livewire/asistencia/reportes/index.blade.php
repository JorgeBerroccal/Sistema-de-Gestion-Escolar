<div>
    <h1 class="mb-4 text-2xl font-semibold">Reportes de asistencia</h1>

    @if (! $elegida)
        <p class="rounded bg-white p-4 text-center text-gray-500 shadow">No hay secciones para mostrar.</p>
    @else
        <div class="mb-4 flex flex-wrap gap-4">
            <div>
                <label for="seccion" class="mr-2 text-sm font-medium">Sección</label>
                <select id="seccion" wire:model.live="seccion" class="rounded border border-gray-300 px-3 py-2">
                    @foreach ($secciones as $s) <option value="{{ $s->id }}" @selected($elegida->id === $s->id)>{{ $s->anio->nombre }} · {{ $s->grado->nivel->nombre }} · {{ $s->grado->nombre }} {{ $s->identificador }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="periodo" class="mr-2 text-sm font-medium">Periodo</label>
                <select id="periodo" wire:model.live="periodo" class="rounded border border-gray-300 px-3 py-2">
                    <option value="">Rango libre</option>
                    @foreach ($periodos as $p) <option value="{{ $p->id }}">{{ $p->nombre }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="desde" class="mr-2 text-sm font-medium">Desde</label>
                <input id="desde" type="date" wire:model.live="desde" value="{{ $rangoDesde->toDateString() }}" class="rounded border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label for="hasta" class="mr-2 text-sm font-medium">Hasta</label>
                <input id="hasta" type="date" wire:model.live="hasta" value="{{ $rangoHasta->toDateString() }}" class="rounded border border-gray-300 px-3 py-2">
            </div>
        </div>

        <section class="mb-6 rounded bg-white p-4 shadow" aria-labelledby="totales">
            <h2 id="totales" class="mb-2 text-lg font-semibold">Totales del {{ $rangoDesde->format('d/m/Y') }} al {{ $rangoHasta->format('d/m/Y') }}</h2>
            @if ($resumen['registrados'] === 0)
                <p class="text-gray-500">Sin datos de asistencia en este rango.</p>
            @else
                <ul class="flex flex-wrap gap-6 text-sm">
                    @foreach ($resumen['por_estado'] as $fila) <li>{{ $fila->nombre }}: <strong>{{ $fila->total }}</strong></li> @endforeach
                    <li>Asistencia: <strong>{{ $resumen['porcentaje'] === null ? 'sin datos' : $resumen['porcentaje'].' %' }}</strong></li>
                </ul>
            @endif
        </section>

        <table class="w-full bg-white text-left shadow">
            <thead class="bg-gray-200 text-sm">
                <tr><th class="p-2">Alumno</th><th class="p-2">Faltas</th><th class="p-2">Justificadas</th><th class="p-2">Asistencia</th><th class="p-2">Detalle</th></tr>
            </thead>
            <tbody>
                @forelse ($ranking as $fila)
                    <tr wire:key="ranking-{{ $fila->alumno_id }}" class="border-t">
                        <td class="p-2">{{ $fila->apellidos }}, {{ $fila->nombres }}</td>
                        <td class="p-2">{{ $fila->faltas }}</td>
                        <td class="p-2">{{ $fila->justificadas }}</td>
                        <td class="p-2">@php($pct = \App\Asistencia\ResumenAsistencia::porcentaje((int) $fila->asistencias, (int) $fila->faltas)){{ $pct === null ? 'sin datos' : $pct.' %' }}</td>
                        <td class="p-2"><a href="{{ route('asistencia.reportes.alumno', [$elegida, $fila->alumno_id, 'desde' => $rangoDesde->toDateString(), 'hasta' => $rangoHasta->toDateString()]) }}" class="underline">Ver días</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Sin datos de asistencia en este rango.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $ranking->links() }}</div>
    @endif
</div>
