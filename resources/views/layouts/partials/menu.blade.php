@auth
    @php
        $sueltos = \App\Support\Menu::sueltosPara(auth()->user());
        $grupos = \App\Support\Menu::gruposPara(auth()->user());
        $claseItem = fn (bool $activo) => 'flex items-center gap-3 rounded-md px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 '.($activo ? 'bg-slate-700 font-semibold text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white');
    @endphp
    <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Menú principal">
        @foreach ($sueltos as $item)
            <a href="{{ route($item['ruta']) }}" class="{{ $claseItem(\App\Support\Menu::esActivo($item)) }}" @if (\App\Support\Menu::esActivo($item)) aria-current="page" @endif>
                <x-icono :nombre="$item['icono']" /><span>{{ $item['etiqueta'] }}</span>
            </a>
        @endforeach

        @foreach ($grupos as $grupo)
            @php($hayActivo = collect($grupo['items'])->contains(fn ($i) => \App\Support\Menu::esActivo($i)))
            <div x-data="{ abierto: @js($hayActivo) }" class="pt-1">
                <button type="button" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto.toString()"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400">
                    <x-icono :nombre="$grupo['icono']" class="size-4" />
                    <span class="flex-1 text-left">{{ $grupo['nombre'] }}</span>
                    <x-icono nombre="chevron-down" class="size-4 transition" x-bind:class="abierto && 'rotate-180'" />
                </button>
                <ul x-show="abierto" x-cloak class="mt-1 space-y-1 border-l border-slate-700 pl-2 ml-4">
                    @foreach ($grupo['items'] as $item)
                        <li>
                            <a href="{{ route($item['ruta']) }}" class="{{ $claseItem(\App\Support\Menu::esActivo($item)) }}" @if (\App\Support\Menu::esActivo($item)) aria-current="page" @endif>
                                <x-icono :nombre="$item['icono']" class="size-4" /><span>{{ $item['etiqueta'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
@endauth
