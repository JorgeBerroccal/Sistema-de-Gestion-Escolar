@props(['href' => null, 'icono' => null, 'titulo' => null])
@php($etiqueta = $href ? 'a' : 'div')
<{{ $etiqueta }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['block rounded-lg border border-slate-200 bg-white p-4 shadow-sm', 'transition hover:border-indigo-400 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500' => $href]) }}>
    @if ($icono || $titulo)
        <div class="flex items-center gap-3">
            @if ($icono)
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><x-icono :nombre="$icono" class="size-6" /></span>
            @endif
            @if ($titulo)
                <span class="font-semibold text-slate-900">{{ $titulo }}</span>
            @endif
        </div>
    @endif
    @if (! $slot->isEmpty())
        <div @class(['mt-2' => $icono || $titulo, 'text-sm text-slate-600'])>{{ $slot }}</div>
    @endif
</{{ $etiqueta }}>
