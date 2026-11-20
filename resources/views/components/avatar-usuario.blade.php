@props(['usuario'])
@php
    // Color derivado del nombre completo. Todos son fondos oscuros (700-800) con texto blanco: contraste de 5:1 o más.
    $colores = ['bg-indigo-700', 'bg-emerald-700', 'bg-rose-700', 'bg-violet-700', 'bg-teal-700', 'bg-sky-800', 'bg-amber-800', 'bg-fuchsia-800', 'bg-cyan-800'];
    $color = $colores[crc32(mb_strtolower($usuario->nombre_completo)) % count($colores)];
@endphp
<span {{ $attributes->class(['flex size-9 shrink-0 select-none items-center justify-center rounded-full text-sm font-semibold text-white', $color]) }} aria-hidden="true" data-avatar-usuario>{{ $usuario->iniciales }}</span>
