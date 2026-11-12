<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleta de notas</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 4px; }
        .centro { text-align: center; }
        .muted { color: #555; }
        .marca { text-align: center; font-size: 22px; font-weight: bold; color: #b91c1c; border: 2px solid #b91c1c; padding: 4px; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #999; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; }
        .num { text-align: center; white-space: nowrap; }
        .datos td { border: none; padding: 1px 4px; }
        .total td { font-weight: bold; background: #f3f4f6; }
        .pie { margin-top: 14px; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    <div class="centro">
        <h1>{{ $institucion->nombre ?: 'Institución educativa' }}</h1>
        <div class="muted">
            @if ($institucion->codigo_modular) Código modular: {{ $institucion->codigo_modular }} @endif
            @if ($institucion->ugel_dre) · {{ $institucion->ugel_dre }} @endif
        </div>
        <h1 style="margin-top:8px">Boleta de notas — {{ $titulo }}</h1>
    </div>

    @if ($borrador)
        <div class="marca">BORRADOR — periodo aún no publicado</div>
    @endif

    <table class="datos">
        <tr>
            <td><strong>Alumno:</strong> {{ $estudiante->nombre_completo }}</td>
            <td><strong>Año escolar:</strong> {{ $anio?->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Grado y sección:</strong> @if ($seccion) {{ $seccion->grado->nivel->nombre }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }} @else — @endif</td>
            <td><strong>Escala de calificación:</strong> {{ $escala->nombre }}</td>
        </tr>
    </table>

    <h2>Notas</h2>
    @if ($modo === 'periodo')
        <table>
            <thead><tr><th>Curso</th><th>Criterios (peso · nota)</th><th class="num">Promedio</th></tr></thead>
            <tbody>
                @forelse ($cursos as $fila)
                    <tr>
                        <td>{{ $fila['curso']->nombre }}</td>
                        <td>
                            @forelse ($fila['criterios'] as $d)
                                {{ $d['criterio']->nombre }} ({{ \App\Support\Formato::recortado($d['criterio']->peso) }} %): <strong>{{ $d['nota'] ? \App\Support\Formato::recortado($d['nota']->valor) : '—' }}</strong><br>
                            @empty
                                <span class="muted">sin notas</span>
                            @endforelse
                        </td>
                        <td class="num">{{ $fila['etiqueta'] ?? 'sin notas' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Sin cursos registrados.</td></tr>
                @endforelse
                <tr class="total"><td colspan="2">Promedio general del periodo</td><td class="num">{{ $general['etiqueta'] ?? 'sin notas' }}</td></tr>
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>Curso</th>
                    @foreach ($periodos as $p) <th class="num">{{ $p->nombre }}</th> @endforeach
                    <th class="num">Promedio anual</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cursos as $fila)
                    <tr>
                        <td>{{ $fila['curso']->nombre }}</td>
                        @foreach ($periodos as $p)
                            @php($promedio = $fila['promedios'][$p->id] ?? null)
                            <td class="num">{{ $promedio === null ? 'sin notas' : $etiquetar($promedio) }}</td>
                        @endforeach
                        <td class="num">{{ $fila['etiqueta'] ?? 'sin notas' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $periodos->count() + 2 }}" class="muted">Sin cursos registrados.</td></tr>
                @endforelse
                <tr class="total"><td colspan="{{ $periodos->count() + 1 }}">Promedio general anual</td><td class="num">{{ $general['etiqueta'] ?? 'sin notas' }}</td></tr>
            </tbody>
        </table>
    @endif

    <h2>Asistencia</h2>
    @if ($asistencia['registrados'] === 0)
        <p class="muted">sin datos de asistencia en el periodo.</p>
    @else
        <table>
            <thead><tr><th>Registros</th>@foreach ($asistencia['por_estado'] as $estado) <th class="num">{{ $estado->nombre }}</th> @endforeach<th class="num">% de asistencia</th></tr></thead>
            <tbody>
                <tr>
                    <td class="num">{{ $asistencia['registrados'] }}</td>
                    @foreach ($asistencia['por_estado'] as $estado) <td class="num">{{ $estado->total }}</td> @endforeach
                    <td class="num">{{ $asistencia['porcentaje'] === null ? 'sin datos' : \App\Support\Formato::recortado($asistencia['porcentaje']).' %' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <p class="pie">
        @if ($literal) Con la escala literal, el calificativo es la letra más cercana al promedio ponderado de los equivalentes numéricos configurados por la institución. @endif
        Documento generado el {{ $generado->format('d/m/Y H:i') }}.
    </p>
</body>
</html>
