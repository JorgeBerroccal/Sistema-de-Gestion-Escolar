<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $curso?->exists ? 'Editar curso' : 'Crear curso' }}</h1>

    <form wire:submit="guardar" class="max-w-2xl space-y-4 rounded bg-white p-6 shadow">
        @error('general')
            <p class="rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        <div>
            <label for="nivel_id" class="block text-sm font-medium">Nivel</label>
            <select id="nivel_id" wire:model.live="nivel_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                <option value="">Elige un nivel</option>
                @foreach ($niveles as $nivel) <option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option> @endforeach
            </select>
            @error('nivel_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="nombre" class="block text-sm font-medium">Nombre</label>
            <input id="nombre" type="text" wire:model="nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="block text-sm font-medium">Grados en que se dicta</legend>
            @forelse ($gradosDelNivel as $grado)
                <label class="mr-4 inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="grados" value="{{ $grado->id }}"> {{ $grado->nombre }}
                </label>
            @empty
                <p class="text-sm text-gray-500">Elige un nivel con grados registrados.</p>
            @endforelse
            @error('grados') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('grados.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Guardar</button>
            <a href="{{ route('academico.cursos.index') }}" class="px-4 py-2 underline">Cancelar</a>
        </div>
    </form>
</div>
