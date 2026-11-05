<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $ficha->nombre_completo }}</h1>
        @can('matricula.ver_todas')
            <a href="{{ route('matricula.alumnos.index') }}" class="underline">Volver a alumnos</a>
        @else
            <a href="{{ route('matricula.propios.index') }}" class="underline">Volver</a>
        @endcan
    </div>

    @if ($mensaje !== '')
        <p class="mb-4 rounded bg-green-100 p-3 text-sm text-green-800" role="status">{{ $mensaje }}</p>
    @endif
    @error('general')
        <p class="mb-4 rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <section class="mb-6 rounded bg-white p-6 shadow">
        <h2 class="mb-3 text-lg font-semibold">Datos del alumno</h2>
        <dl class="grid gap-2 sm:grid-cols-2">
            <div><dt class="text-sm text-gray-500">Documento</dt><dd>{{ $ficha->tipoDocumento->nombre }} {{ $ficha->numero_documento }}</dd></div>
            <div><dt class="text-sm text-gray-500">Fecha de nacimiento</dt><dd>{{ $ficha->fecha_nacimiento->format('d/m/Y') }}</dd></div>
            <div><dt class="text-sm text-gray-500">Sexo</dt><dd>{{ $ficha->sexo->etiqueta() }}</dd></div>
            <div><dt class="text-sm text-gray-500">Ficha</dt><dd>{{ $ficha->activo ? 'Activa' : 'Desactivada' }}</dd></div>
        </dl>
        @if ($puedeGestionar)
            <div class="mt-4 flex gap-3">
                <a href="{{ route('matricula.alumnos.edit', $ficha) }}" class="underline">Editar</a>
                @if ($ficha->activo)
                    <button type="button" wire:click="desactivar" class="underline">Desactivar</button>
                @else
                    <button type="button" wire:click="reactivar" class="underline">Reactivar</button>
                @endif
                @unless ($historial->isNotEmpty())
                    <button type="button" wire:click="eliminar" wire:confirm="¿Eliminar definitivamente?" class="text-red-700 underline">Eliminar</button>
                @endunless
            </div>
        @endif
    </section>

    @if ($veVinculos)
        <section class="mb-6 rounded bg-white p-6 shadow">
            <h2 class="mb-3 text-lg font-semibold">Usuario del alumno</h2>
            @if ($usuarioAlumno)
                <p>{{ $usuarioAlumno->nombre_completo }} ({{ $usuarioAlumno->username }})
                    @if ($puedeGestionar) <button type="button" wire:click="quitarUsuario" class="ml-2 underline">Quitar vínculo</button> @endif
                </p>
            @else
                <p class="text-sm text-gray-500">Sin usuario vinculado.</p>
                @if ($puedeGestionar)
                    <form wire:submit="vincularUsuario" class="mt-3 flex flex-wrap items-end gap-3">
                        <div>
                            <label for="usuarioAlumnoId" class="block text-sm font-medium">Usuario</label>
                            <select id="usuarioAlumnoId" wire:model="usuarioAlumnoId" class="mt-1 rounded border border-gray-300 px-3 py-2">
                                <option value="">Elige un usuario</option>
                                @foreach ($usuariosAlumno as $u) <option value="{{ $u->id }}">{{ $u->nombre_completo }} ({{ $u->username }})</option> @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Vincular</button>
                    </form>
                @endif
            @endif
        </section>

        <section class="mb-6 rounded bg-white p-6 shadow">
            <h2 class="mb-3 text-lg font-semibold">Apoderados</h2>
            <ul class="mb-3">
                @forelse ($apoderados as $apoderado)
                    <li wire:key="apoderado-{{ $apoderado->id }}" class="py-1">
                        {{ $apoderado->nombre_completo }} ({{ $apoderado->username }}) · {{ ucfirst($apoderado->pivot->parentesco) }}
                        @if ($puedeGestionar) <button type="button" wire:click="quitarApoderado({{ $apoderado->id }})" class="ml-2 underline">Quitar</button> @endif
                    </li>
                @empty
                    <li class="text-sm text-gray-500">Sin apoderados.</li>
                @endforelse
            </ul>
            @if ($puedeGestionar)
                <form wire:submit="agregarApoderado" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="apoderadoId" class="block text-sm font-medium">Usuario apoderado</label>
                        <select id="apoderadoId" wire:model="apoderadoId" class="mt-1 rounded border border-gray-300 px-3 py-2">
                            <option value="">Elige un usuario</option>
                            @foreach ($apoderadosElegibles as $u) <option value="{{ $u->id }}">{{ $u->nombre_completo }} ({{ $u->username }})</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="parentesco" class="block text-sm font-medium">Parentesco</label>
                        <select id="parentesco" wire:model="parentesco" class="mt-1 rounded border border-gray-300 px-3 py-2">
                            <option value="">Elige</option>
                            @foreach ($parentescos as $p) <option value="{{ $p->value }}">{{ $p->etiqueta() }}</option> @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Agregar apoderado</button>
                </form>
            @endif
        </section>
    @endif

    <section class="mb-6 rounded bg-white p-6 shadow">
        <h2 class="mb-3 text-lg font-semibold">Historial de matrículas</h2>
        <table class="w-full text-left">
            <thead class="bg-gray-200 text-sm">
                <tr><th class="p-2">Año</th><th class="p-2">Nivel</th><th class="p-2">Grado y sección</th><th class="p-2">Matrícula</th><th class="p-2">Estado</th><th class="p-2">Fecha del estado</th><th class="p-2">Motivo</th>@if ($puedeGestionar)<th class="p-2">Acciones</th>@endif</tr>
            </thead>
            <tbody>
                @forelse ($historial as $matricula)
                    <tr wire:key="historial-{{ $matricula->id }}" class="border-t">
                        <td class="p-2">{{ $matricula->seccion->anio->nombre }}</td>
                        <td class="p-2">{{ $matricula->seccion->grado->nivel->nombre }}</td>
                        <td class="p-2">{{ $matricula->seccion->grado->nombre }} {{ $matricula->seccion->identificador }}</td>
                        <td class="p-2">{{ $matricula->fecha_matricula->format('d/m/Y') }}</td>
                        <td class="p-2">{{ $matricula->estado->etiqueta() }}</td>
                        <td class="p-2">{{ $matricula->fecha_estado?->format('d/m/Y') }}</td>
                        <td class="p-2">{{ $matricula->motivo }}</td>
                        @if ($puedeGestionar)
                            <td class="flex gap-3 p-2">
                                @if ($matricula->estado->esActiva())
                                    <button type="button" wire:click="iniciarCambio({{ $matricula->id }}, 'retirar')" class="underline">Retirar</button>
                                    <button type="button" wire:click="iniciarCambio({{ $matricula->id }}, 'trasladar')" class="underline">Trasladar</button>
                                @else
                                    <button type="button" wire:click="corregir({{ $matricula->id }})" class="underline">Corregir a matriculado</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4 text-center text-gray-500">Sin matrículas.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($puedeGestionar && $cambioMatriculaId)
            <form wire:submit="confirmarCambio" class="mt-4 max-w-xl space-y-3 rounded border border-gray-300 p-4">
                <h3 class="font-medium">{{ $cambioTipo === 'trasladar' ? 'Trasladar' : 'Retirar' }} matrícula</h3>
                <div>
                    <label for="cambioFecha" class="block text-sm font-medium">Fecha</label>
                    <input id="cambioFecha" type="date" wire:model="cambioFecha" class="mt-1 rounded border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="cambioMotivo" class="block text-sm font-medium">Motivo (opcional)</label>
                    <input id="cambioMotivo" type="text" wire:model="cambioMotivo" maxlength="255" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Confirmar</button>
                    <button type="button" wire:click="cancelarCambio" class="px-4 py-2 underline">Cancelar</button>
                </div>
            </form>
        @endif
    </section>

    @if ($puedeGestionar)
        <section class="mb-6 rounded bg-white p-6 shadow">
            <h2 class="mb-3 text-lg font-semibold">Matricular{{ $vigente ? ' en '.$vigente->nombre : '' }}</h2>
            @if ($vigente)
                <form wire:submit="matricular" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="seccionId" class="block text-sm font-medium">Sección</label>
                        <select id="seccionId" wire:model="seccionId" class="mt-1 rounded border border-gray-300 px-3 py-2">
                            <option value="">Elige una sección</option>
                            @foreach ($secciones as $seccion) <option value="{{ $seccion->id }}">{{ $seccion->grado->nivel->nombre }} · {{ $seccion->grado->nombre }} {{ $seccion->identificador }}</option> @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Matricular</button>
                </form>
            @else
                <p class="text-sm text-gray-500">No hay un año escolar vigente.</p>
            @endif
        </section>
    @endif
</div>
