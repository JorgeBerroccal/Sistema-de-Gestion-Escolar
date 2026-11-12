<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Asistencia de {{ $alumno->nombre_completo }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }} ({{ $seccion->anio->nombre }})</h1>
        <a href="{{ route('asistencia.reportes.index', ['seccion' => $seccion->id, 'desde' => $rangoDesde->toDateString(), 'hasta' => $rangoHasta->toDateString()]) }}" class="underline">Volver al reporte</a>
    </div>

    <section class="mb-6 rounded bg-white p-4 shadow" aria-labelledby="resumen">
        <h2 id="resumen" class="mb-2 text-lg font-semibold">Del {{ $rangoDesde->format('d/m/Y') }} al {{ $rangoHasta->format('d/m/Y') }}</h2>
        @if ($resumen['registrados'] === 0)
            <p class="text-gray-500">Sin datos de asistencia en este rango.</p>
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
                <tr><td colspan="4" class="p-4 text-center text-gray-500">Sin datos de asistencia en este rango.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $registros->links() }}</div>
</div>
