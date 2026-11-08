<div>
    <h1 class="mb-4 text-2xl font-semibold">Tomar asistencia</h1>

    @unless ($vigente)
        <p class="mb-4 rounded bg-yellow-100 p-3 text-sm text-yellow-800">No hay un año escolar vigente: no se puede registrar asistencia.</p>
    @endunless

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Año</th><th class="p-2">Nivel</th><th class="p-2">Grado y sección</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($secciones as $seccion)
                <tr wire:key="seccion-{{ $seccion->id }}" class="border-t">
                    <td class="p-2">{{ $seccion->anio->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nivel->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nombre }} {{ $seccion->identificador }}</td>
                    <td class="p-2"><a href="{{ route('asistencia.tomar.show', $seccion) }}" class="underline">Tomar asistencia</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No hay secciones para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $secciones->links() }}</div>
</div>
