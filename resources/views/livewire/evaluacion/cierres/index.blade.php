<div>
    <h1 class="mb-4 text-2xl font-semibold">Cierres de periodo</h1>

    @if ($mensaje !== '')
        <p class="mb-4 rounded bg-green-100 p-3 text-sm text-green-800" role="status">{{ $mensaje }}</p>
    @endif
    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    @unless ($vigente)
        <p class="mb-4 rounded bg-yellow-100 p-3 text-sm text-yellow-800">No hay un año escolar vigente.</p>
    @endunless

    <p class="mb-3 text-sm text-gray-600">Cerrar un periodo impide que los Docentes modifiquen sus notas y publica notas y boleta para Alumnos y Padres de Familia.</p>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr>
                <th class="p-2">Grado y sección</th>
                @foreach ($periodos as $p) <th class="p-2">{{ $p->nombre }}</th> @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($secciones as $seccion)
                <tr wire:key="seccion-{{ $seccion->id }}" class="border-t">
                    <td class="p-2">{{ $seccion->grado->nombre }} {{ $seccion->identificador }}</td>
                    @foreach ($periodos as $p)
                        @php($cierre = $cierres->get($seccion->id.'-'.$p->id))
                        <td class="p-2" wire:key="celda-{{ $seccion->id }}-{{ $p->id }}">
                            @if ($cierre)
                                <span class="mr-2 rounded bg-gray-200 px-2 py-1 text-xs">Cerrado</span>
                                <button type="button" wire:click="reabrir({{ $seccion->id }}, {{ $p->id }})"
                                    wire:confirm="Los Docentes podrán editar y las familias dejarán de ver este periodo. ¿Reabrir?"
                                    class="text-sm underline">Reabrir</button>
                            @else
                                <span class="mr-2 rounded bg-green-100 px-2 py-1 text-xs">Abierto</span>
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
    </table>

    <div class="mt-4">{{ $secciones->links() }}</div>
</div>
