<div>
    <h1 class="mb-4 text-2xl font-semibold">Asistente virtual</h1>

    <p class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="note" data-aviso-ia>
        Las respuestas las genera una IA y pueden contener errores. Para datos oficiales consulta Mis notas, Mi asistencia o tu boleta.
    </p>

    <section class="mb-4 space-y-3 rounded bg-white p-4 shadow" aria-label="Conversación" aria-live="polite">
        @forelse ($turnos as $i => $turno)
            <div wire:key="turno-{{ $i }}" class="{{ $turno['rol'] === 'usuario' ? 'text-right' : '' }}">
                <p class="inline-block max-w-xl whitespace-pre-line rounded px-3 py-2 text-left text-sm
                    {{ $turno['rol'] === 'usuario' ? 'bg-blue-100' : ($turno['tipo'] === 'respuesta' ? 'bg-gray-100' : 'bg-yellow-50 text-yellow-900') }}">{{ $turno['texto'] }}</p>
            </div>
        @empty
            <p class="text-gray-500">Pregunta por tus notas, tu asistencia, tu matrícula o tu sección. Por ejemplo: «¿Cómo voy en mis cursos?» o «¿Cuántas faltas tengo?».</p>
        @endforelse
    </section>

    <form wire:submit="enviar" class="flex gap-2">
        <label for="mensaje" class="sr-only">Tu pregunta</label>
        <input id="mensaje" type="text" wire:model="mensaje" maxlength="{{ config('asistente.mensaje.maximo') }}" autocomplete="off"
               class="flex-1 rounded border border-gray-300 px-3 py-2" placeholder="Escribe tu pregunta">
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white" wire:loading.attr="disabled" wire:target="enviar">
            <span wire:loading.remove wire:target="enviar">Enviar</span><span wire:loading wire:target="enviar">Consultando…</span>
        </button>
    </form>
</div>
