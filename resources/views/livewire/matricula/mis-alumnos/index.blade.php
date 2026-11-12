<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $titulo }}</h1>

    <table class="w-full bg-white text-left shadow">
        <thead class="bg-gray-200 text-sm">
            <tr><th class="p-2">Apellidos y nombres</th><th class="p-2">Documento</th><th class="p-2">Matrícula {{ $vigente?->nombre }}</th><th class="p-2">Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($alumnos as $alumno)
                <tr wire:key="propio-{{ $alumno->id }}" class="border-t">
                    <td class="p-2">{{ $alumno->nombre_completo }}</td>
                    <td class="p-2">{{ $alumno->tipoDocumento->codigo }} {{ $alumno->numero_documento }}</td>
                    <td class="p-2">
                        @php($actual = $alumno->matriculas->first())
                        {{ $actual ? $actual->seccion->grado->nivel->nombre.' · '.$actual->seccion->grado->nombre.' '.$actual->seccion->identificador : 'Sin matrícula activa' }}
                    </td>
                    <td class="p-2"><a href="{{ route('matricula.alumnos.show', $alumno) }}" class="underline">Ver ficha e historial</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No tienes alumnos vinculados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $alumnos->links() }}</div>
</div>
