@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">Mi cuenta</h1>

    @if ($usuario->debe_cambiar_password)
        <x-alerta tipo="aviso" class="mt-4 max-w-xl">Debes definir una contraseña nueva antes de usar el sistema.</x-alerta>
    @endif

    <x-tarjeta class="mt-4 max-w-xl">
        <h2 class="mb-3 text-lg font-semibold">Datos de la cuenta</h2>
        <dl class="grid gap-2 text-sm sm:grid-cols-[8rem_1fr]">
            <dt class="text-slate-500">Nombres</dt><dd>{{ $usuario->nombres }}</dd>
            <dt class="text-slate-500">Apellidos</dt><dd>{{ $usuario->apellidos }}</dd>
            <dt class="text-slate-500">Usuario</dt><dd>{{ $usuario->username }}</dd>
        </dl>
        <p class="mt-3 text-xs text-slate-500">Los datos oficiales los administra el Administrador del sistema.</p>
    </x-tarjeta>

    <x-tarjeta class="mt-4 max-w-xl">
        <h2 class="mb-3 text-lg font-semibold">Cambiar contraseña</h2>
        <form method="POST" action="{{ route('cuenta.password.actualizar') }}" class="space-y-4">
            @csrf
            @method('PUT')
            @foreach ([['password_actual', 'Contraseña actual', 'current-password'], ['password', 'Contraseña nueva', 'new-password'], ['password_confirmation', 'Confirmar contraseña nueva', 'new-password']] as [$campo, $etiqueta, $auto])
                <div>
                    <label for="{{ $campo }}" class="block text-sm font-medium">{{ $etiqueta }}</label>
                    <input id="{{ $campo }}" name="{{ $campo }}" type="password" required autocomplete="{{ $auto }}"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @if ($campo !== 'password_confirmation')
                        @error($campo) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>
            @endforeach
            <p class="text-xs text-slate-500">Mínimo 8 caracteres. Al cambiarla se cerrarán tus sesiones en otros dispositivos.</p>
            <x-boton type="submit">Cambiar contraseña</x-boton>
        </form>
    </x-tarjeta>
@endsection
