<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div x-data="{ abierto: false }" x-on:keydown.escape.window="abierto = false">
        @auth
            <div x-show="abierto" x-cloak x-on:click="abierto = false" class="fixed inset-0 z-30 bg-slate-900/50 md:hidden" aria-hidden="true"></div>
            <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 transition-transform md:translate-x-0" x-bind:class="abierto && 'translate-x-0'">
                <div class="flex items-center justify-between border-b border-slate-800 px-4 py-4">
                    <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-2 font-semibold text-white">
                        <x-icono nombre="academic-cap" class="size-6 text-indigo-400" />
                        <span class="truncate">{{ \App\Models\Institucion::actual()->nombre ?: config('app.name') }}</span>
                    </a>
                    <button type="button" x-on:click="abierto = false" class="rounded-md p-1 text-slate-300 hover:text-white md:hidden" aria-label="Cerrar el menú"><x-icono nombre="x-mark" class="size-5" /></button>
                </div>
                @include('layouts.partials.menu')
            </aside>
        @endauth

        <div @class(['md:pl-64' => auth()->check()])>
            @auth
                @include('layouts.partials.encabezado')
            @else
                <header class="bg-white px-4 py-3 shadow"><span class="font-semibold">{{ config('app.name') }}</span></header>
            @endauth

            <main class="mx-auto max-w-6xl p-4 md:p-6">
                @yield('content')
                {{ $slot ?? '' }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
