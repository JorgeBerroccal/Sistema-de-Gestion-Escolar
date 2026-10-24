<div>
    <h1 class="mb-4 text-2xl font-semibold">Asignación</h1>

    <dl class="max-w-xl space-y-2 rounded bg-white p-6 shadow">
        <div><dt class="text-sm text-gray-500">Año escolar</dt><dd>{{ $asignacion->seccion->anio->nombre }}</dd></div>
        <div><dt class="text-sm text-gray-500">Nivel</dt><dd>{{ $asignacion->seccion->grado->nivel->nombre }}</dd></div>
        <div><dt class="text-sm text-gray-500">Grado y sección</dt><dd>{{ $asignacion->seccion->grado->nombre }} {{ $asignacion->seccion->identificador }}</dd></div>
        <div><dt class="text-sm text-gray-500">Curso</dt><dd>{{ $asignacion->curso->nombre }}</dd></div>
        <div>
            <dt class="text-sm text-gray-500">Docente</dt>
            <dd>
                {{ $asignacion->usuario->nombre_completo }}
                @if ($asignacion->pendiente_reasignar)
                    <span class="ml-1 rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Pendiente de reasignar</span>
                @endif
            </dd>
        </div>
    </dl>

    <p class="mt-4">
        @can('asignaciones.ver_todas')
            <a href="{{ route('academico.asignaciones.index') }}" class="underline">Volver a Asignaciones</a>
        @elsecan('asignaciones.ver_propias')
            <a href="{{ route('academico.asignaciones.propias') }}" class="underline">Volver a Mis asignaciones</a>
        @endcan
    </p>
</div>
