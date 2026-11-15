<?php

use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

const AVISO = 'Las respuestas las genera una IA y pueden contener errores';

it('el aviso de IA aparece en toda pantalla del chat: vacía, con respuesta, con aclaración y con error', function () {
    $e = escenarioAsistente();

    $c = Livewire::actingAs($e['padre'])->test(Index::class)->assertSee(AVISO)->assertSee('Mis notas')->assertSee('boleta');

    $c->set('mensaje', '¿cómo va?')->call('enviar')->assertSee('¿De cuál de tus estudiantes')->assertSee(AVISO);
    $c->set('mensaje', 'Ana')->call('enviar')->assertSee('Ana Alfa')->assertSee(AVISO);

    $e['falso']->fallar();
    $c->set('mensaje', 'Ana otra vez')->call('enviar')->assertSee('no está disponible')->assertSee(AVISO);
});

it('el aviso también está en la página completa', function () {
    $e = escenarioAsistente();

    $this->actingAs($e['alumno_usuario'])->get(route('asistente.index'))->assertSee(AVISO);
});
