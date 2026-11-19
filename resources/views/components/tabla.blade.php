{{-- Contenedor con desplazamiento propio: la página nunca desborda a 360 px. --}}
<div {{ $attributes->only('class')->class('overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm') }}>
    <table {{ $attributes->except('class')->class('w-full text-left text-sm') }}>{{ $slot }}</table>
</div>
