<div>
    <h1 class="mb-4 text-2xl font-semibold">Registro de notas</h1>

    @unless ($vigente)
        <x-alerta tipo="aviso" class="mb-4">No hay un año escolar vigente: no se pueden registrar notas.</x-alerta>
    @endunless

    <x-tabla>
        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
            <tr><th class="p-2">Grado y sección</th><th class="p-2">Curso</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($pares as $par)
                <tr wire:key="par-{{ $par->seccion_id }}-{{ $par->curso_id }}" class="border-t">
                    <td class="p-2">{{ $par->nivel }} · {{ $par->grado }} {{ $par->identificador }}</td>
                    <td class="p-2">{{ $par->curso }}</td>
                    <td class="p-2"><a href="{{ route('notas.registro.show', ['seccion' => $par->seccion_id, 'curso' => $par->curso_id]) }}" class="underline">Registrar notas</a></td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-4 text-center text-gray-500">No hay cursos y secciones para mostrar.</td></tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div class="mt-4">{{ $pares->links() }}</div>
</div>
