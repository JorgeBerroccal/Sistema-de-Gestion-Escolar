<div>
    <div class="mb-4 flex items-center gap-3">
        <x-asistente-avatar class="size-12" />
        <div>
            <h1 class="text-2xl font-semibold" data-asistente-nombre>{{ $nombre }}</h1>
            <p class="text-sm text-slate-500">Asistente virtual</p>
        </div>
    </div>

    <x-alerta tipo="aviso" class="mb-4" role="note" data-aviso-ia>
        Las respuestas las genera una IA y pueden contener errores. Para datos oficiales consulta Mis notas, Mi asistencia o tu boleta.
    </x-alerta>

    @if ($avisoSinVinculo)
        <x-alerta tipo="aviso" class="mb-4" data-sin-vinculo>{{ $avisoSinVinculo }}</x-alerta>
    @endif

    @if ($noDisponible)
        <x-alerta tipo="error" data-asistente-no-disponible>{{ $noDisponible }}</x-alerta>
    @else
    <section class="mb-4 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-label="Conversación" aria-live="polite">
        <div class="flex items-start gap-2" data-saludo>
            <x-asistente-avatar />
            <p class="max-w-xl rounded-lg bg-slate-100 px-3 py-2 text-sm">
                Hola, soy {{ $nombre }}, el asistente virtual del colegio. Puedo responder sobre tus notas, tu asistencia, tu matrícula y tu sección. Soy una inteligencia artificial, no una persona.
                <span class="mt-1 block text-slate-600">Por ejemplo: «¿Cómo voy en mis cursos?» o «¿Cuántas faltas tengo?».</span>
            </p>
        </div>

        @if ($turnos === [] && ! $avisoSinVinculo)
            <div class="flex flex-wrap gap-2 pl-11" data-sugerencias>
                @foreach ($sugerencias as $i => $sugerencia)
                    <button type="button" wire:click="sugerida({{ $i }})" wire:loading.attr="disabled" wire:target="enviar,sugerida"
                            class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-800 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50">{{ $sugerencia }}</button>
                @endforeach
            </div>
        @endif

        @foreach ($turnos as $i => $turno)
            <div wire:key="turno-{{ $i }}" class="flex items-start gap-2 {{ $turno['rol'] === 'usuario' ? 'justify-end' : '' }}">
                @if ($turno['rol'] !== 'usuario')
                    <x-asistente-avatar />
                @endif
                <div class="max-w-xl rounded-lg px-3 py-2 text-left text-sm [&_a]:underline [&_ol]:ml-5 [&_ol]:list-decimal [&_p+p]:mt-2 [&_p+ul]:mt-2 [&_p+ol]:mt-2 [&_ul]:ml-5 [&_ul]:list-disc [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold
                    {{ $turno['rol'] === 'usuario' ? 'bg-indigo-100' : ($turno['tipo'] === 'respuesta' ? 'bg-slate-100' : 'bg-amber-50 text-amber-900') }}">
                    @if ($turno['rol'] === 'usuario')
                        <p class="whitespace-pre-line">{{ $turno['texto'] }}</p>
                    @else
                        {{ \App\Support\MarkdownSeguro::html($turno['texto']) }}
                    @endif
                </div>
            </div>
        @endforeach

        <div wire:loading.flex wire:target="enviar,sugerida" class="items-center gap-2 text-sm text-slate-500" data-escribiendo>
            <x-asistente-avatar class="size-7" /><span>{{ $nombre }} está escribiendo…</span>
        </div>
    </section>

    <form wire:submit="enviar" class="flex gap-2">
        <label for="mensaje" class="sr-only">Tu pregunta</label>
        <input id="mensaje" type="text" wire:model="mensaje" maxlength="{{ config('asistente.mensaje.maximo') }}" autocomplete="off"
               class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" placeholder="Escribe tu pregunta">
        <x-boton wire:loading.attr="disabled" wire:target="enviar,sugerida">
            <span wire:loading.remove wire:target="enviar,sugerida">Enviar</span><span wire:loading wire:target="enviar,sugerida">Consultando…</span>
        </x-boton>
    </form>
    @endif
</div>
