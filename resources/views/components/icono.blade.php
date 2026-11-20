@props(['nombre'])
@php
    // Heroicons (MIT, ver components/icono/LICENSE-heroicons.txt), con el trazo heredado del texto.
    static $cache = [];
    $cache[$nombre] ??= (function () use ($nombre) {
        $archivo = resource_path("views/components/icono/{$nombre}.svg");
        $svg = is_file($archivo) ? file_get_contents($archivo) : '';

        return str_replace(['stroke="#0F172A"', 'width="24" height="24"'], ['stroke="currentColor"', ''], $svg);
    })();
@endphp
{!! str_replace('<svg ', '<svg aria-hidden="true" '.$attributes->merge(['class' => 'size-5 shrink-0'])->toHtml().' ', $cache[$nombre]) !!}
