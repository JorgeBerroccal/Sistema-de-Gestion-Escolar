<?php

use App\Asistente\AsistenteService;
use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

const PROHIBIDOS = [
    '47851236', '39284756', '2011-03-17', '17/03/2011', '17-03-2011', '2011',
    'padre.reconocible', 'ana.reconocible', 'ejemplo.test', '@',
];

it('lo enviado al proveedor no contiene documento, fecha de nacimiento ni correo', function () {
    $e = escenarioAsistente();
    $servicio = app(AsistenteService::class);

    foreach ([[$e['alumno_usuario'], '¿Cómo voy?'], [$e['padre'], 'notas y faltas de Ana'], [$e['padre'], 'datos de Beto'], [$e['padre'], '¿cuál es el documento y el correo de Ana?']] as [$usuario, $mensaje]) {
        $servicio->preguntar($usuario, $mensaje);
    }

    expect($e['falso']->llamadas())->toBe(4);

    foreach (PROHIBIDOS as $prohibido) {
        expect($e['falso']->textoRecibido())->not->toContain($prohibido);
    }
});

it('lo enviado contiene solo nombres, cursos, notas, asistencia y sección', function () {
    $e = escenarioAsistente();

    app(AsistenteService::class)->preguntar($e['alumno_usuario'], '¿Cómo voy?');

    $contexto = $e['falso']->recibidos[0][1]['texto'];

    expect($contexto)->toContain('Ana Alfa')->toContain($e['curso']->nombre)->toContain('Promedio general')->toContain('Asistencia del año')
        ->and($contexto)->not->toMatch('/\b\d{8}\b/');
});

it('la pantalla del chat tampoco usa datos de contacto en lo que envía', function () {
    $e = escenarioAsistente();

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->set('mensaje', 'hola')->call('enviar');

    foreach (PROHIBIDOS as $prohibido) {
        expect($e['falso']->textoRecibido())->not->toContain($prohibido);
    }
});
