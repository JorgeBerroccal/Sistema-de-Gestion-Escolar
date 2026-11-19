<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Años escolares</h1>
        @can('academico.gestionar')
            <x-boton href="{{ route('academico.anios.create') }}">Crear año escolar</x-boton>
        @endcan
    </div>

    @error('general')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    <x-tabla>
        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
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
    </x-tabla>

    <div class="mt-4">{{ $anios->links() }}</div>
</div>
