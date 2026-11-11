<div>
    <h1 class="mb-4 text-2xl font-semibold">Mis notas</h1>

    @if ($aviso)
        <p class="mb-4 rounded bg-yellow-100 p-3 text-sm text-yellow-800" role="alert">{{ $aviso }}</p>
    @endif

    @if ($hijos->isEmpty())
        <p class="rounded bg-white p-4 text-gray-500 shadow">No tienes alumnos vinculados.</p>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-4">
            @if ($hijos->count() > 1)
                <div>
                    <label for="hijo" class="mr-2 text-sm font-medium">Estudiante</label>
                    <select id="hijo" wire:model.live="hijo" class="rounded border border-gray-300 px-3 py-2">
                        @foreach ($hijos as $h) <option value="{{ $h->id }}">{{ $h->nombre_completo }}</option> @endforeach
                    </select>
                </div>
            @else
                <p class="font-medium">{{ $alumnoActual->nombre_completo }}</p>
            @endif

            @if ($periodos->isNotEmpty())
                <div>
                    <label for="periodo" class="mr-2 text-sm font-medium">Periodo</label>
                    <select id="periodo" wire:model.live="periodo" class="rounded border border-gray-300 px-3 py-2">
                        @foreach ($periodos as $p) <option value="{{ $p->id }}">{{ $p->anio->nombre }} · {{ $p->nombre }}</option> @endforeach
                        <option value="anual">Anual</option>
                    </select>
                </div>
            @endif
        </div>

        @if ($periodos->isEmpty())
            <p class="rounded bg-white p-4 text-gray-500 shadow">Aún no hay periodos publicados para este alumno.</p>
        @elseif ($anual)
            <table class="w-full bg-white text-left shadow">
                <thead class="bg-gray-200 text-sm"><tr><th class="p-2">Curso</th><th class="p-2">Promedio anual</th></tr></thead>
                <tbody>
                    @foreach ($anual['cursos'] as $fila)
                        <tr class="border-t"><td class="p-2">{{ $fila['curso']->nombre }}</td><td class="p-2">{{ $fila['etiqueta'] ?? 'sin notas' }}</td></tr>
                    @endforeach
                    <tr class="border-t bg-gray-50 font-medium"><td class="p-2">Promedio general</td><td class="p-2">{{ $anual['etiqueta'] ?? 'sin notas' }}</td></tr>
                </tbody>
            </table>
            @if ($hayBoleta)
                <p class="mt-4"><a href="{{ route('boletas.descargar', [$alumnoActual->id, 'anual']) }}" class="underline">Descargar boleta anual</a></p>
            @endif
        @elseif ($datos)
            <p class="mb-2 text-sm text-gray-600">{{ $datos['seccion']->grado->nombre }} {{ $datos['seccion']->identificador }} · {{ $datos['periodo']->nombre }}</p>
            @foreach ($datos['cursos'] as $fila)
                <section class="mb-4 bg-white p-4 shadow" wire:key="curso-{{ $fila['curso']->id }}">
                    <h2 class="mb-2 text-lg font-semibold">{{ $fila['curso']->nombre }}</h2>
                    @if ($fila['criterios']->isEmpty())
                        <p class="text-gray-500">sin notas</p>
                    @else
                        <table class="w-full text-left text-sm">
                            <thead><tr><th class="py-1">Criterio</th><th class="py-1">Peso</th><th class="py-1">Nota</th></tr></thead>
                            <tbody>
                                @foreach ($fila['criterios'] as $d)
                                    <tr class="border-t"><td class="py-1">{{ $d['criterio']->nombre }}</td><td class="py-1">{{ rtrim(rtrim((string) $d['criterio']->peso, '0'), '.') }} %</td><td class="py-1">{{ $d['nota']?->valor ?? '—' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-2 font-medium">Promedio del periodo: {{ $fila['etiqueta'] ?? 'sin notas' }}</p>
                    @endif
                </section>
            @endforeach
            <p class="font-medium">Promedio general del periodo: {{ $datos['etiqueta'] ?? 'sin notas' }}</p>
            @if ($hayBoleta)
                <p class="mt-4">
                    <a href="{{ route('boletas.descargar', [$alumnoActual->id, $datos['periodo']->id]) }}" class="mr-4 underline">Descargar boleta</a>
                    <a href="{{ route('boletas.descargar', [$alumnoActual->id, 'anual']) }}" class="underline">Descargar boleta anual</a>
                </p>
            @endif
        @endif
    @endif
</div>
