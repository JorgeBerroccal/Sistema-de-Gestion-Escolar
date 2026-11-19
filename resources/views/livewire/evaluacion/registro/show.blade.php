<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Notas de {{ $curso->nombre }} · {{ $seccion->etiqueta }} ({{ $seccion->anio->nombre }})</h1>
        <a href="{{ route('notas.registro.index') }}" class="underline">Volver</a>
    </div>

    @if (session('estado'))
        <x-alerta tipo="exito" class="mb-4">{{ session('estado') }}</x-alerta>
    @endif
    @if ($errors->has('notas'))
        <x-alerta tipo="error" class="mb-4">
            <ul class="list-disc pl-5">
                @foreach ($errors->get('notas') as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </x-alerta>
    @endif
    @error('criterios')
        <x-alerta tipo="error" class="mb-4">{{ $message }}</x-alerta>
    @enderror

    @if ($periodos->isNotEmpty())
        <form method="GET" action="{{ route('notas.registro.show', ['seccion' => $seccion, 'curso' => $curso]) }}" class="mb-3">
            <label for="periodo" class="mr-2 text-sm font-medium">Periodo</label>
            <select id="periodo" name="periodo" onchange="this.form.submit()" class="rounded border border-gray-300 px-3 py-2">
                @foreach ($periodos as $p)
                    <option value="{{ $p->id }}" @selected($periodoActual && $periodoActual->id === $p->id)>{{ $p->nombre }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="ml-2 underline">Ver</button></noscript>
            @if ($cerrado) <span class="ml-3 rounded bg-gray-200 px-2 py-1 text-sm">Periodo cerrado</span> @endif
        </form>
    @endif

    @if ($aviso)
        <x-alerta tipo="aviso" class="mb-4">{{ $aviso }}</x-alerta>
    @endif

    @if ($periodoActual)
        <section class="mb-6 bg-white p-4 shadow">
            <h2 class="mb-2 text-lg font-semibold">Criterios de evaluación</h2>
            @php($total = collect($criterios)->sum(fn ($c) => (float) str_replace(',', '.', (string) $c['peso'])))
            <form wire:submit="guardarCriterios">
                @foreach ($criterios as $i => $criterio)
                    <div wire:key="criterio-{{ $i }}" class="mb-2 flex items-center gap-2">
                        <input type="text" maxlength="60" aria-label="Nombre del criterio {{ $i + 1 }}" wire:model.live="criterios.{{ $i }}.nombre" @disabled($soloLectura) class="w-64 rounded border border-gray-300 px-2 py-1">
                        <input type="text" inputmode="decimal" aria-label="Peso del criterio {{ $i + 1 }} (%)" wire:model.live="criterios.{{ $i }}.peso" @disabled($soloLectura) class="w-24 rounded border border-gray-300 px-2 py-1">
                        <span class="text-sm">%</span>
                        @unless ($soloLectura)
                            <button type="button" wire:click="quitarCriterio({{ $i }})"
                                @if ($criterio['id']) wire:confirm="Quitar este criterio borra las notas ya registradas en él. ¿Continuar?" @endif
                                class="text-sm underline">Quitar</button>
                        @endunless
                    </div>
                @endforeach
                <p class="mb-2 text-sm {{ abs($total - 100) < 0.005 ? 'text-green-700' : 'text-red-700' }}">Total de pesos: {{ \App\Support\Formato::recortado(number_format($total, 2, '.', '')) }} % (debe sumar 100 %)</p>
                @unless ($soloLectura)
                    <button type="button" wire:click="agregarCriterio" class="mr-3 rounded border border-gray-400 px-3 py-1">Agregar criterio</button>
                    <x-boton type="submit">Guardar criterios</x-boton>
                @endunless
            </form>
        </section>

        <section wire:ignore>
            <h2 class="mb-2 text-lg font-semibold">Notas</h2>
            @if ($escala)
                <p class="mb-2 text-sm text-gray-600">
                    Escala {{ $escala->nombre }}:
                    @if ($calculo->esLiteral()) {{ implode(', ', $escala->valores) }}
                    @else de {{ $escala->valores['minimo'] ?? 0 }} a {{ $escala->valores['maximo'] ?? 20 }} @endif
                    · Celda vacía = sin nota.
                </p>
            @endif

            @if ($guardados->isEmpty())
                <p class="rounded bg-white p-4 text-gray-500 shadow">Define y guarda los criterios para registrar notas.</p>
            @else
                <form method="POST" action="{{ route('notas.registro.guardar', ['seccion' => $seccion, 'curso' => $curso]) }}">
                    @csrf
                    <input type="hidden" name="periodo" value="{{ $periodoActual->id }}">
                    <x-tabla>
                        <thead class="bg-slate-100 text-xs uppercase text-slate-600">
                            <tr>
                                <th class="p-2">Apellidos y nombres</th>
                                @foreach ($guardados as $c)
                                    <th class="p-2">{{ $c->nombre }} ({{ rtrim(rtrim((string) $c->peso, '0'), '.') }} %)</th>
                                @endforeach
                                <th class="p-2">Promedio</th>
                                @if ($verBoleta)<th class="p-2">Boleta</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alumnos as $alumno)
                                <tr class="border-t">
                                    <td class="p-2">{{ $alumno->nombre_completo }}</td>
                                    @foreach ($guardados as $c)
                                        <td class="p-2">
                                            <input type="text" maxlength="20" name="notas[{{ $alumno->id }}][{{ $c->id }}]"
                                                aria-label="{{ $c->nombre }} de {{ $alumno->nombre_completo }}"
                                                value="{{ old("notas.{$alumno->id}.{$c->id}", $notas[$alumno->id][$c->id]->valor ?? '') }}"
                                                @disabled($soloLectura) class="w-20 rounded border border-gray-300 px-2 py-1">
                                        </td>
                                    @endforeach
                                    <td class="p-2">{{ $calculo?->etiqueta($promedios[$alumno->id] ?? null) ?? 'sin notas' }}</td>
                                    @if ($verBoleta)
                                        <td class="p-2"><a href="{{ route('boletas.descargar', [$alumno->id, $periodoActual->id]) }}" class="underline">Boleta</a></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $guardados->count() + 3 }}" class="p-4 text-center text-gray-500">La sección no tiene alumnos matriculados.</td></tr>
                            @endforelse
                        </tbody>
                    </x-tabla>

                    @if (! $soloLectura && $alumnos->isNotEmpty())
                        <x-boton type="submit" class="mt-4">Guardar notas</x-boton>
                    @endif
                </form>
            @endif
        </section>
    @endif
</div>
