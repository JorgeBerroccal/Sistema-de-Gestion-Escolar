<div>
    <h1 class="mb-4 text-2xl font-semibold">Cierres de periodo</h1>

    @if ($mensaje !== '')
        <x-alerta tipo="exito" class="mb-4">{{ $mensaje }}</x-alerta>
    @endif
    @error('general')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    @unless ($vigente)
        <x-alerta tipo="aviso" class="mb-4">No hay un año escolar vigente.</x-alerta>
    @endunless

    <p class="mb-3 text-sm text-gray-600">Cerrar un periodo impide que los Docentes modifiquen sus notas y publica notas y boleta para Alumnos y Padres de Familia.</p>

    <x-tabla>
        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
            <tr>
                <th class="p-2">Grado y sección</th>
                @foreach ($periodos as $p) <th class="p-2">{{ $p->nombre }}</th> @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($secciones as $seccion)
                <tr wire:key="seccion-{{ $seccion->id }}" class="border-t">
                    <td class="p-2">{{ $seccion->etiqueta }}</td>
                    @foreach ($periodos as $p)
                        @php($cierre = $cierres->get($seccion->id.'-'.$p->id))
                        <td class="p-2" wire:key="celda-{{ $seccion->id }}-{{ $p->id }}">
                            @if ($cierre)
                                <span class="mr-2 rounded bg-gray-200 px-2 py-1 text-xs">Cerrado</span>
                                <button type="button" wire:click="reabrir({{ $seccion->id }}, {{ $p->id }})"
                                    wire:confirm="Los Docentes podrán editar y las familias dejarán de ver este periodo. ¿Reabrir?"
                                    class="text-sm underline">Reabrir</button>
                            @else
                                <x-etiqueta tipo="exito" class="mr-2">Abierto</x-etiqueta>
                                <button type="button" wire:click="cerrar({{ $seccion->id }}, {{ $p->id }})"
                                    wire:confirm="Se bloqueará la edición de los Docentes y se publicarán las notas. ¿Cerrar?"
                                    class="text-sm underline">Cerrar</button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ $periodos->count() + 1 }}" class="p-4 text-center text-gray-500">No hay secciones para mostrar.</td></tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div class="mt-4">{{ $secciones->links() }}</div>
</div>
