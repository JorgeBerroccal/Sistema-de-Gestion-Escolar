@props(['tipo' => 'neutro'])
@php
    $clases = match ($tipo) {
        'exito' => 'bg-green-100 text-green-800',
        'aviso' => 'bg-amber-100 text-amber-800',
        'error' => 'bg-red-100 text-red-800',
        'info' => 'bg-indigo-100 text-indigo-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $clases]) }}>{{ $slot }}</span>
