@extends('layouts.guest')

@section('content')
    <div class="rounded bg-white p-6 shadow">
        <h1 class="mb-4 text-xl font-semibold">{{ config('app.name') }} — Iniciar sesión</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="login" class="block text-sm font-medium">Correo o nombre de usuario</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                       autocomplete="username" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Contraseña</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if ($errors->has('login'))
                <p class="text-sm text-red-600" role="alert">{{ $errors->first('login') }}</p>
            @endif

            <button type="submit" class="w-full rounded bg-gray-800 px-4 py-2 text-white">Ingresar</button>
        </form>
    </div>
@endsection
