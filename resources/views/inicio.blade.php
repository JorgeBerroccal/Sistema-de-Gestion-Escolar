@extends('layouts.app')

@section('content')
    @php($grupos = \App\Support\Menu::gruposPara(auth()->user()))
    <h1 class="text-2xl font-semibold">Bienvenido, {{ auth()->user()->nombre_completo }}</h1>
    <p class="mt-1 text-slate-600">Estos son los módulos del sistema a los que tienes acceso.</p>

    @forelse ($grupos as $grupo)
        <section class="mt-6" aria-labelledby="grupo-{{ $loop->index }}">
            <h2 id="grupo-{{ $loop->index }}" class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                <x-icono :nombre="$grupo['icono']" class="size-4" />{{ $grupo['nombre'] }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($grupo['items'] as $item)
                    <x-tarjeta :href="route($item['ruta'])" :icono="$item['icono']" :titulo="$item['etiqueta']" data-tarjeta>{{ $item['descripcion'] }}</x-tarjeta>
                @endforeach
            </div>
        </section>
    @empty
        <x-alerta tipo="aviso" class="mt-6">Tu cuenta todavía no tiene módulos habilitados. Comunícate con la administración del colegio.</x-alerta>
    @endforelse
@endsection
