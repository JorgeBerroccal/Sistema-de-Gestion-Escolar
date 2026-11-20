<?php

use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

it('el nombre por defecto es Yachay', function () {
    expect(config('asistente.nombre'))->toBe('Yachay');
});

it('el chat muestra el nombre configurado, el avatar, el saludo y las preguntas sugeridas', function () {
    $e = escenarioAsistente();
    config(['asistente.nombre' => 'Amaru']);

    Livewire::actingAs($e['padre'])->test(Index::class)
        ->assertSeeHtml('data-asistente-nombre')->assertSee('Amaru')
        ->assertSee('Hola, soy Amaru')->assertSee('no una persona')
        ->assertSeeHtml('data-sugerencias')->assertSeeHtml('aria-label="Avatar del asistente virtual"')
        ->assertSee('Amaru está escribiendo…')
        ->assertSee('Las respuestas las genera una IA y pueden contener errores');
});

it('la identidad usa Yachay si no se configura nada y el aviso de IA se mantiene', function () {
    $e = escenarioAsistente();

    $this->actingAs($e['alumno_usuario'])->get(route('asistente.index'))
        ->assertSee('Hola, soy Yachay')->assertSee('Las respuestas las genera una IA');
});

it('un clic en una pregunta sugerida la envía y oculta las sugerencias', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->call('sugerida', 1)
        ->assertSee('¿Cuántas faltas tengo?')->assertDontSeeHtml('data-sugerencias');
});

it('un índice de sugerencia inexistente no envía nada', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->call('sugerida', 99)->assertSet('turnos', []);
});
