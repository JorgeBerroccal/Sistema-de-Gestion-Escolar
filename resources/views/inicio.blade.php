@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">Bienvenido, {{ auth()->user()->nombre_completo }}</h1>
    <p class="mt-2 text-gray-600">Aquí aparecerán los módulos del sistema a los que tienes acceso.</p>
@endsection
