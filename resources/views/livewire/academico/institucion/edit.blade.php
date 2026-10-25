<div class="max-w-xl">
    <h1 class="mb-4 text-2xl font-semibold">Institución</h1>

    @if (session('exito'))
        <p class="mb-4 rounded bg-green-100 p-3 text-sm text-green-700" role="status">{{ session('exito') }}</p>
    @endif

    @can('academico.gestionar')
        <form wire:submit="guardar" class="space-y-4 rounded bg-white p-6 shadow">
            <div>
                <label for="nombre" class="block text-sm font-medium">Nombre</label>
                <input id="nombre" type="text" wire:model="nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="codigo_modular" class="block text-sm font-medium">Código modular</label>
                <input id="codigo_modular" type="text" wire:model="codigo_modular" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('codigo_modular') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ugel_dre" class="block text-sm font-medium">UGEL o dirección regional</label>
                <input id="ugel_dre" type="text" wire:model="ugel_dre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('ugel_dre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <fieldset>
                <legend class="block text-sm font-medium">Niveles que atiende</legend>
                @forelse ($catalogoNiveles as $nivel)
                    <label class="mt-1 flex items-center gap-2">
                        <input type="checkbox" wire:model="niveles" value="{{ $nivel->id }}"> {{ $nivel->nombre }}
                    </label>
                @empty
                    <p class="mt-1 text-sm text-gray-500">No hay niveles registrados. <a href="{{ route('academico.niveles.index') }}" class="underline">Crea los niveles</a> primero.</p>
                @endforelse
                @error('niveles') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('niveles.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>
            <div>
                <label for="escala" class="block text-sm font-medium">Escala de calificación</label>
                <select id="escala" wire:model="escala_calificacion_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Sin elegir</option>
                    @foreach ($escalas as $escala)
                        <option value="{{ $escala->id }}">{{ $escala->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Solo se guarda la configuración; este módulo no califica.</p>
                @error('escala_calificacion_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Guardar</button>
        </form>
    @else
        <dl class="space-y-3 rounded bg-white p-6 shadow">
            <div><dt class="text-sm font-medium">Nombre</dt><dd>{{ $nombre ?: '—' }}</dd></div>
            <div><dt class="text-sm font-medium">Código modular</dt><dd>{{ $codigo_modular ?: '—' }}</dd></div>
            <div><dt class="text-sm font-medium">UGEL o dirección regional</dt><dd>{{ $ugel_dre ?: '—' }}</dd></div>
            <div><dt class="text-sm font-medium">Niveles que atiende</dt>
                <dd>{{ $catalogoNiveles->whereIn('id', array_map('intval', $niveles))->pluck('nombre')->join(', ') ?: '—' }}</dd></div>
            <div><dt class="text-sm font-medium">Escala de calificación</dt><dd>{{ $escalaActual?->nombre ?? '—' }}</dd></div>
        </dl>
    @endcan
</div>
