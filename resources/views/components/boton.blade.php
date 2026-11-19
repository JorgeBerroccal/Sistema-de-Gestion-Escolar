@props(['tipo' => 'primario', 'href' => null])
@php
    $clases = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50 '.match ($tipo) {
        'secundario' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
        'peligro' => 'bg-red-600 text-white hover:bg-red-700',
        default => 'bg-indigo-700 text-white hover:bg-indigo-800',
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($clases) }}>{{ $slot }}</button>
@endif
