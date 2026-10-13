@auth
    <nav class="bg-gray-800 px-4 py-2 text-white" aria-label="Menú principal">
        <ul class="flex gap-4">
            @foreach (config('menu') as $item)
                @continue(! Route::has($item['ruta']))
                @if ($item['permiso'] === null)
                    <li><a href="{{ route($item['ruta']) }}" @class(['font-bold underline' => request()->routeIs($item['ruta'].'*')])>{{ $item['etiqueta'] }}</a></li>
                @else
                    @can($item['permiso'])
                        <li><a href="{{ route($item['ruta']) }}" @class(['font-bold underline' => request()->routeIs($item['ruta'].'*')])>{{ $item['etiqueta'] }}</a></li>
                    @endcan
                @endif
            @endforeach
        </ul>
    </nav>
@endauth
