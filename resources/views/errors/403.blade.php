@extends('layouts.app', ['title' => 'Acceso denegado'])

@section('content')
    <h1 class="text-2xl font-bold">Acceso denegado</h1>
    <p class="mt-2">No tienes permiso para realizar esta acción.</p>
    <a href="{{ url('/inicio') }}" class="mt-4 inline-block underline">Volver al inicio</a>
@endsection
