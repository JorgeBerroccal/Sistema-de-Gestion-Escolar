{{-- Contenedor con desplazamiento propio: la página nunca desborda a 360 px. --}}
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table {{ $attributes->class('w-full text-left text-sm') }}>{{ $slot }}</table>
</div>
