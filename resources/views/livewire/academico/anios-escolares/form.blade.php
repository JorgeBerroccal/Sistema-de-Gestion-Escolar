@php($editando = (bool) $anio?->exists)
<div>
    <h1 class="mb-4 text-2xl font-semibold">{{ $editando ? 'Editar año escolar' : 'Crear año escolar' }}</h1>

    <form wire:submit="guardar" class="max-w-3xl space-y-4 rounded bg-white p-6 shadow">
        @error('general')
            <p class="rounded bg-red-100 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="block text-sm font-medium">Nombre</label>
                <input id="nombre" type="text" wire:model="nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="fecha_inicio" class="block text-sm font-medium">Fecha de inicio</label>
                <input id="fecha_inicio" type="date" wire:model="fecha_inicio" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('fecha_inicio') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="fecha_fin" class="block text-sm font-medium">Fecha de fin</label>
                <input id="fecha_fin" type="date" wire:model="fecha_fin" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('fecha_fin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tipo_periodo" class="block text-sm font-medium">Tipo de periodo</label>
                <select id="tipo_periodo" wire:model.live="tipo_periodo" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->value }}">{{ $tipo->etiqueta() }}s ({{ $tipo->cantidadPeriodos() }})</option>
                    @endforeach
                </select>
                @error('tipo_periodo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">Periodos de evaluación</legend>
            <p class="text-xs text-gray-500">Dentro del año, en orden y sin superponerse: cada día pertenece a un solo periodo. Pueden quedar días libres entre ellos.</p>
            @foreach ($periodos as $i => $periodo)
                <div wire:key="periodo-{{ $i }}" class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="periodo-nombre-{{ $i }}" class="block text-sm">Periodo {{ $i + 1 }}</label>
                        <input id="periodo-nombre-{{ $i }}" type="text" wire:model="periodos.{{ $i }}.nombre" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        @error("periodos.$i.nombre") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="periodo-inicio-{{ $i }}" class="block text-sm">Inicio</label>
                        <input id="periodo-inicio-{{ $i }}" type="date" wire:model="periodos.{{ $i }}.fecha_inicio" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        @error("periodos.$i.fecha_inicio") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="periodo-fin-{{ $i }}" class="block text-sm">Fin</label>
                        <input id="periodo-fin-{{ $i }}" type="date" wire:model="periodos.{{ $i }}.fecha_fin" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        @error("periodos.$i.fecha_fin") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endforeach
        </fieldset>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Guardar</button>
            <a href="{{ route('academico.anios.index') }}" class="px-4 py-2 underline">Cancelar</a>
        </div>
    </form>
</div>
