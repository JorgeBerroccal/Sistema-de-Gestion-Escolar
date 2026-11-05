<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $veTodas ? 'Nóminas' : 'Mis secciones' }}</h1>

    <div class="mb-3">
        <label for="filtroAnio" class="mr-2 text-sm font-medium">Año</label>
        <select id="filtroAnio" wire:model.live="filtroAnio" class="rounded border border-gray-300 px-3 py-2">
            <option value="0" @selected($anioId === '0')>Todos</option>
            @foreach ($anios as $anio)
                <option value="{{ $anio->id }}" @selected($anioId === (string) $anio->id)>{{ $anio->nombre }}{{ $anio->vigente ? ' (vigente)' : '' }}</option>
            @endforeach
        </select>
    </div>

    @unless ($vigente)
        <p class="mb-4 rounded bg-yellow-100 p-3 text-sm text-yellow-800">No hay un año escolar vigente: no se puede matricular hasta marcar uno.</p>
    @endunless

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Año</th><th class="p-2">Nivel</th><th class="p-2">Grado y sección</th><th class="p-2">Matriculados</th><th class="p-2">Estado</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($secciones as $seccion)
                <tr wire:key="seccion-{{ $seccion->id }}" class="border-t">
                    <td class="p-2">{{ $seccion->anio->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nivel->nombre }}</td>
                    <td class="p-2">{{ $seccion->grado->nombre }} {{ $seccion->identificador }}</td>
                    <td class="p-2">{{ $seccion->matriculados_count }}</td>
                    <td class="p-2">{{ $seccion->activo ? 'Activa' : 'Desactivada' }}</td>
                    <td class="p-2"><a href="{{ route('matricula.nominas.show', $seccion) }}" class="underline">Ver nómina</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">No hay secciones para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $secciones->links() }}</div>
</div>
