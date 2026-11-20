<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $titulo }}</h1>

    @if ($alumnos->isEmpty())
        <x-alerta tipo="aviso" data-sin-vinculo>{{ $aviso }}</x-alerta>
    @elseif ($esAlumno)
        {{-- Alumno: su propia ficha como tarjeta, sin tabla ni columna Acciones. --}}
        @foreach ($alumnos as $alumno)
            @php($actual = $alumno->matriculas->first())
            <x-tarjeta wire:key="propio-{{ $alumno->id }}" icono="identification" :titulo="$alumno->nombre_completo" class="max-w-xl" data-ficha-propia>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Documento</dt>
                        <dd class="text-slate-900">{{ $alumno->tipoDocumento->codigo }} {{ $alumno->numero_documento }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Matrícula {{ $vigente?->nombre }}</dt>
                        <dd class="text-slate-900">
                            @if ($actual)
                                {{ $actual->seccion->grado->nivel->nombre }} · {{ $actual->seccion->grado->nombre }} {{ $actual->seccion->identificador }}
                            @else
                                <x-etiqueta tipo="aviso">Sin matrícula activa</x-etiqueta>
                            @endif
                        </dd>
                    </div>
                </dl>
                <p class="mt-4"><x-boton tipo="secundario" :href="route('matricula.alumnos.show', $alumno)">Ver mi historial de matrículas</x-boton></p>
            </x-tarjeta>
        @endforeach
    @else
        <x-tabla>
            <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                <tr><th class="p-3">Apellidos y nombres</th><th class="p-3">Documento</th><th class="p-3">Matrícula {{ $vigente?->nombre }}</th><th class="p-3">Acciones</th></tr>
            </thead>
            <tbody>
                @foreach ($alumnos as $alumno)
                    <tr wire:key="propio-{{ $alumno->id }}" class="border-t border-slate-200">
                        <td class="p-3">{{ $alumno->nombre_completo }}</td>
                        <td class="p-3">{{ $alumno->tipoDocumento->codigo }} {{ $alumno->numero_documento }}</td>
                        <td class="p-3">
                            @php($actual = $alumno->matriculas->first())
                            {{ $actual ? $actual->seccion->grado->nivel->nombre.' · '.$actual->seccion->grado->nombre.' '.$actual->seccion->identificador : 'Sin matrícula activa' }}
                        </td>
                        <td class="p-3"><a href="{{ route('matricula.alumnos.show', $alumno) }}" class="text-indigo-700 underline">Ver ficha e historial</a></td>
                    </tr>
                @endforeach
            </tbody>
        </x-tabla>
    @endif

    <div class="mt-4">{{ $alumnos->links() }}</div>
</div>
