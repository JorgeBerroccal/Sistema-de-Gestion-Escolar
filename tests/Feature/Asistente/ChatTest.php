<?php

use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

it('un alumno chatea y ve su pregunta y la respuesta', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->set('mensaje', '¿Cómo voy?')->call('enviar')
        ->assertSee('¿Cómo voy?')->assertSee('Promedio general: 13,00')->assertSet('mensaje', '');
});

it('un padre con dos hijos recibe la aclaración y luego la respuesta del hijo elegido', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['padre'])->test(Index::class)
        ->set('mensaje', '¿Cómo va?')->call('enviar')->assertSee('¿De cuál de tus estudiantes')
        ->set('mensaje', 'Ana')->call('enviar')->assertSee('Ana Alfa');
});

it('un mensaje vacío no agrega la pregunta pero sí avisa', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->set('mensaje', '  ')->call('enviar')->assertSee('Escribe tu pregunta')->assertCount('turnos', 1);
});

it('la ruta responde a un alumno y reiniciar la página vacía el chat', function () {
    $e = escenarioAsistente();

    $this->actingAs($e['alumno_usuario'])->get(route('asistente.index'))->assertOk()->assertSee('Asistente virtual');

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->assertSet('turnos', []);
});

it('sin el permiso la pantalla da 403', function () {
    $sin = usuarioConPermisos(['notas.ver_propia']);

    $this->actingAs($sin)->get(route('asistente.index'))->assertForbidden();
});
