<?php

use App\Asistente\AsistenteService;
use App\Asistente\ResultadoAsistente as R;
use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

it('con el proveedor caído el usuario recibe un mensaje comprensible, sin detalles técnicos', function () {
    $e = escenarioAsistente();
    $e['falso']->fallar();

    $r = app(AsistenteService::class)->preguntar($e['alumno_usuario'], '¿cómo voy?');

    expect($r->tipo)->toBe(R::ERROR)->and($r->texto)->toContain('no está disponible')->toContain('resto del sistema sigue funcionando')
        ->not->toContain('Fallo simulado')->not->toContain('Exception');
});

it('la pantalla del chat sigue usable tras una caída y se recupera cuando el proveedor vuelve', function () {
    $e = escenarioAsistente();
    $e['falso']->fallar();

    $c = Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->set('mensaje', 'uno')->call('enviar')->assertOk()->assertSee('no está disponible');

    $e['falso']->fallar(false);
    $c->set('mensaje', 'dos')->call('enviar')->assertSee('Promedio general: 13,00');
});

it('el resto del sistema sigue funcionando durante la caída', function () {
    $e = escenarioAsistente();
    $e['falso']->fallar();

    $this->actingAs($e['alumno_usuario'])->get('/inicio')->assertOk();
    $this->actingAs($e['alumno_usuario'])->get(route('notas.propia.index'))->assertOk();
});

it('un modelo que falla no deja turnos de error en el contexto de la siguiente pregunta', function () {
    $e = escenarioAsistente();
    $e['falso']->fallar();

    $c = Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->set('mensaje', 'uno')->call('enviar');
    $e['falso']->fallar(false);
    $c->set('mensaje', 'dos')->call('enviar');

    $ultimo = collect($e['falso']->recibidos)->last();

    expect(collect($ultimo)->pluck('texto')->implode("\n"))->not->toContain('no está disponible');
});
