@props(['tipo' => 'info'])
@php
    $clases = match ($tipo) {
        'exito' => 'border-green-200 bg-green-50 text-green-900',
        'aviso' => 'border-amber-200 bg-amber-50 text-amber-900',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        default => 'border-sky-200 bg-sky-50 text-sky-900',
    };
@endphp
<div role="{{ $tipo === 'error' ? 'alert' : 'status' }}" {{ $attributes->class(['rounded-lg border p-4 text-sm', $clases]) }}>{{ $slot }}</div>
