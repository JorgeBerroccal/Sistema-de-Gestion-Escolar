<div>
    <h1 class="mb-4 text-2xl font-semibold">Reportes de notas</h1>

    <div class="mb-4 flex flex-wrap gap-4">
        <label class="text-sm">Sección
            <select wire:model.live="seccion" class="ml-1 rounded border p-1">
                <option value="">Elige una sección</option>
                @foreach ($secciones as $s)
                    <option value="{{ $s->id }}">{{ $s->grado->nombre }} {{ $s->identificador }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Periodo
            <select wire:model.live="periodo" class="ml-1 rounded border p-1">
                @foreach ($periodos as $p)
                    <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if (! $reporte)
        <p class="rounded bg-gray-100 p-3 text-sm text-gray-600">Elige una sección y un periodo para ver el reporte.</p>
    @elseif ($reporte['sin_datos'])
        <p class="rounded bg-yellow-100 p-3 text-sm text-yellow-800">Sin datos: la sección no tiene notas en este periodo.</p>
    @else
        <h2 class="mb-2 text-lg font-semibold">Por curso</h2>
        <table class="mb-6 w-full bg-white text-left shadow">
            <thead class="bg-gray-200 text-sm"><tr><th class="p-2">Curso</th><th class="p-2">Promedio</th><th class="p-2">Desaprobados</th></tr></thead>
            <tbody>
                @foreach ($reporte['cursos'] as $fila)
                    <tr wire:key="curso-{{ $fila['curso']->id }}" class="border-t">
                        <td class="p-2">{{ $fila['curso']->nombre }}</td>
                        <td class="p-2">{{ $fila['etiqueta'] ?? 'Sin datos' }}</td>
                        <td class="p-2">{{ $fila['desaprobados'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2 class="mb-2 text-lg font-semibold">Orden de mérito</h2>
        <table class="w-full bg-white text-left shadow">
            <thead class="bg-gray-200 text-sm">
                <tr>
                    <th class="p-2">Puesto</th><th class="p-2">Alumno</th>
                    @foreach ($reporte['cursos'] as $fila) <th class="p-2">{{ $fila['curso']->nombre }}</th> @endforeach
                    <th class="p-2">Promedio general</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte['alumnos'] as $fila)
                    <tr wire:key="estudiante-{{ $fila['estudiante']->id }}" class="border-t">
                        <td class="p-2">{{ $fila['puesto'] ?? '—' }}</td>
                        <td class="p-2">{{ $fila['estudiante']->apellidos }}, {{ $fila['estudiante']->nombres }}</td>
                        @foreach ($reporte['cursos'] as $c)
                            <td class="p-2">{{ $fila['promedios'][$c['curso']->id] === null ? '—' : app(\App\Evaluacion\EvaluacionService::class)->calculo()->etiqueta($fila['promedios'][$c['curso']->id]) }}</td>
                        @endforeach
                        <td class="p-2">{{ $fila['etiqueta'] ?? 'Sin notas' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $reporte['alumnos']->links() }}</div>
    @endif
</div>
