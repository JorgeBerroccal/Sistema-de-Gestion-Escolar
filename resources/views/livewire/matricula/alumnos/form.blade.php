<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $alumno?->exists ? 'Editar alumno' : 'Registrar alumno' }}</h1>

    <form wire:submit="guardar" class="max-w-2xl space-y-4 rounded bg-white p-6 shadow">
        @error('general')
            <p class="rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nombres" class="block text-sm font-medium">Nombres</label>
                <input id="nombres" type="text" wire:model="nombres" maxlength="100" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label for="apellidos" class="block text-sm font-medium">Apellidos</label>
                <input id="apellidos" type="text" wire:model="apellidos" maxlength="100" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label for="tipo_documento_id" class="block text-sm font-medium">Tipo de documento</label>
                <select id="tipo_documento_id" wire:model="tipo_documento_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige un tipo</option>
                    @foreach ($tipos as $tipo) <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="numero_documento" class="block text-sm font-medium">Número de documento</label>
                <input id="numero_documento" type="text" wire:model="numero_documento" maxlength="20" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label for="fecha_nacimiento" class="block text-sm font-medium">Fecha de nacimiento</label>
                <input id="fecha_nacimiento" type="date" wire:model="fecha_nacimiento" max="{{ \App\Support\ZonaHoraria::hoy() }}" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label for="sexo" class="block text-sm font-medium">Sexo</label>
                <select id="sexo" wire:model="sexo" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Elige</option>
                    @foreach ($sexos as $s) <option value="{{ $s->value }}">{{ $s->etiqueta() }}</option> @endforeach
                </select>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Guardar</button>
            <a href="{{ route('matricula.alumnos.index') }}" class="px-4 py-2 underline">Cancelar</a>
        </div>
    </form>
</div>
