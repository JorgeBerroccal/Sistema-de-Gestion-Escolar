@extends('layouts.guest')

@section('content')
    <div class="mb-6 flex flex-col items-center text-center">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-indigo-700 text-white shadow"><x-icono nombre="academic-cap" class="size-8" /></span>
        <p class="mt-3 text-lg font-semibold text-slate-900">{{ \App\Models\Institucion::actual()->nombre ?: config('app.name') }}</p>
        <p class="text-sm text-slate-500">Sistema de Gestión Escolar</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-xl font-semibold">Iniciar sesión</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="login" class="block text-sm font-medium">Correo o nombre de usuario</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                       autocomplete="username" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>

            <div x-data="{ ver: false }">
                <label for="password" class="block text-sm font-medium">Contraseña</label>
                <div class="relative mt-1">
                    <input id="password" name="password" x-bind:type="ver ? 'text' : 'password'" type="password" required
                           autocomplete="current-password" class="w-full rounded-md border border-slate-300 py-2 pl-3 pr-11 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <button type="button" x-on:click="ver = ! ver" x-bind:aria-label="ver ? 'Ocultar la contraseña' : 'Mostrar la contraseña'" aria-label="Mostrar la contraseña"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <x-icono nombre="eye" class="size-5" x-show="! ver" />
                        <x-icono nombre="eye-slash" class="size-5" x-show="ver" x-cloak />
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if ($errors->has('login'))
                <x-alerta tipo="error">{{ $errors->first('login') }}</x-alerta>
            @endif

            <x-boton class="w-full">Ingresar</x-boton>
        </form>
    </div>
@endsection
