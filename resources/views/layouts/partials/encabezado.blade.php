@php
    $usuario = auth()->user();
    $anio = \App\Models\AnioEscolar::query()->where('vigente', 1)->value('nombre');
@endphp
<header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
    <button type="button" x-on:click="abierto = true" class="rounded-md p-1 text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 md:hidden" aria-label="Abrir el menú">
        <x-icono nombre="bars-3" class="size-6" />
    </button>
    <x-avatar-usuario :usuario="$usuario" />
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900">{{ $usuario->nombre_completo }}</p>
        <p class="truncate text-xs text-slate-500">{{ $usuario->rol?->nombre }}@if ($anio) · Año escolar {{ $anio }}@endif</p>
    </div>
    <a href="{{ route('cuenta.password') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" title="Mi cuenta">
        <x-icono nombre="user-circle" class="size-4" /><span class="hidden sm:inline">Mi cuenta</span><span class="sr-only sm:hidden">Mi cuenta</span>
    </a>
    <form method="POST" action="{{ url('/logout') }}">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" title="Cerrar sesión">
            <x-icono nombre="arrow-right-on-rectangle" class="size-4" /><span class="hidden sm:inline">Cerrar sesión</span><span class="sr-only sm:hidden">Cerrar sesión</span>
        </button>
    </form>
</header>
