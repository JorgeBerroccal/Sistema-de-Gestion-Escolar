<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 antialiased">
    <header class="flex items-center justify-between bg-white px-4 py-3 shadow">
        <span class="font-semibold">{{ config('app.name') }}</span>
        @auth
            <div class="flex items-center gap-4">
                <span>{{ auth()->user()->nombre_completo }}</span>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="rounded bg-gray-800 px-3 py-1 text-white">Cerrar sesión</button>
                </form>
            </div>
        @endauth
    </header>

    @include('layouts.partials.menu')

    <main class="mx-auto max-w-6xl p-4">
        @yield('content')
        {{ $slot ?? '' }}
    </main>
</body>
</html>
