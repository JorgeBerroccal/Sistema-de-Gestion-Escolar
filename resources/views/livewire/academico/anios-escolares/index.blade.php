<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Años escolares</h1>
        @can('academico.gestionar')
            <a href="{{ route('academico.anios.create') }}" class="rounded bg-gray-800 px-3 py-2 text-white">Crear año escolar</a>
        @endcan
    </div>

    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Año</th>
                <th class="p-2">Inicio</th>
                <th class="p-2">Fin</th>
                <th class="p-2">Periodos</th>
                <th class="p-2">Estado</th>
                <th class="p-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($anios as $anio)
                <tr wire:key="anio-{{ $anio->id }}" class="border-t">
                    <td class="p-2">{{ $anio->nombre }}</td>
                    <td class="p-2">{{ $anio->fecha_inicio->format('d/m/Y') }}</td>
                    <td class="p-2">{{ $anio->fecha_fin->format('d/m/Y') }}</td>
                    <td class="p-2">{{ $anio->periodos_count }} ({{ $anio->tipo_periodo->value }}s)</td>
                    <td class="p-2">
                        @if ($anio->esVigente()) <strong>Vigente</strong> @endif
                        {{ $anio->activo ? '' : 'Inactivo' }}
                    </td>
                    <td class="flex gap-3 p-2">
                        @can('academico.gestionar')
                            <a href="{{ route('academico.anios.edit', $anio) }}" class="underline">Editar</a>
                            @unless ($anio->esVigente())
                                <button type="button" wire:click="marcarVigente({{ $anio->id }})"
                                        wire:confirm="¿Marcar este año como vigente? El año vigente actual dejará de serlo." class="text-green-700 underline">Marcar vigente</button>
                            @endunless
                            @unless ($anio->esVigente())
                                @if ($anio->activo)
                                    <button type="button" wire:click="desactivar({{ $anio->id }})" class="underline">Desactivar</button>
                                @else
                                    <button type="button" wire:click="reactivar({{ $anio->id }})" class="underline">Reactivar</button>
                                @endif
                                @unless ($anio->tieneDependientes())
                                    <button type="button" wire:click="eliminar({{ $anio->id }})" wire:confirm="¿Eliminar definitivamente?" class="text-red-700 underline">Eliminar</button>
                                @endunless
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-center text-gray-500">Aún no hay años escolares.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $anios->links() }}</div>
</div>
