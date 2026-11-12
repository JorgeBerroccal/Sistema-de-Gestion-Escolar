<?php

use App\Asistente\AsistenteService;
use App\Asistente\ResultadoAsistente as R;
use App\Livewire\Asistente\Chat\Index;
use Livewire\Livewire;

function limitar(int $usuarioMinuto = 2, int $globalMinuto = 100): void
{
    config(['asistente.limites' => ['usuario_minuto' => $usuarioMinuto, 'usuario_dia' => 100, 'global_minuto' => $globalMinuto, 'global_dia' => 100]]);
}

it('superado el límite configurado el usuario ve cuándo volver a preguntar y no se llama al proveedor', function () {
    $e = escenarioAsistente();
    limitar(2);
    $servicio = app(AsistenteService::class);

    expect($servicio->preguntar($e['alumno_usuario'], 'uno')->tipo)->toBe(R::RESPUESTA)
        ->and($servicio->preguntar($e['alumno_usuario'], 'dos')->tipo)->toBe(R::RESPUESTA);

    $r = $servicio->preguntar($e['alumno_usuario'], 'tres');

    expect($r->tipo)->toBe(R::LIMITE)->and($r->texto)->toContain('volver a preguntar en')->toMatch('/(segundo|minuto)/')
        ->and($r->segundos)->toBeGreaterThan(0)->and($e['falso']->llamadas())->toBe(2);
});

it('el límite global se aplica aunque cada usuario esté por debajo del suyo', function () {
    $e = escenarioAsistente();
    limitar(10, 2);
    $servicio = app(AsistenteService::class);

    $servicio->preguntar($e['alumno_usuario'], 'uno');
    $servicio->preguntar($e['padre'], 'notas de Ana');

    expect($servicio->preguntar($e['alumno_usuario'], 'dos')->tipo)->toBe(R::LIMITE)->and($e['falso']->llamadas())->toBe(2);
});

it('la aclaración y el «sin datos» no consumen cupo', function () {
    $e = escenarioAsistente();
    limitar(1);
    $servicio = app(AsistenteService::class);

    foreach (range(1, 4) as $i) {
        expect($servicio->preguntar($e['padre'], '¿cómo va?')->tipo)->toBe(R::ACLARACION);
    }

    expect($servicio->preguntar(usuarioConPermisos(['asistente.usar']), 'hola')->tipo)->toBe(R::SIN_DATOS)
        ->and($servicio->preguntar($e['padre'], 'notas de Ana')->tipo)->toBe(R::RESPUESTA);
});

it('los mensajes inválidos tampoco consumen cupo', function () {
    $e = escenarioAsistente();
    limitar(1);
    $servicio = app(AsistenteService::class);

    $servicio->preguntar($e['alumno_usuario'], '');
    $servicio->preguntar($e['alumno_usuario'], '   ');

    expect($servicio->preguntar($e['alumno_usuario'], 'hola')->tipo)->toBe(R::RESPUESTA);
});

it('tras la ventana el usuario puede volver a preguntar', function () {
    $e = escenarioAsistente();
    limitar(1);
    $servicio = app(AsistenteService::class);
    $servicio->preguntar($e['alumno_usuario'], 'uno');
    expect($servicio->preguntar($e['alumno_usuario'], 'dos')->tipo)->toBe(R::LIMITE);

    $this->travel(61)->seconds();

    expect($servicio->preguntar($e['alumno_usuario'], 'tres')->tipo)->toBe(R::RESPUESTA);
});

it('la pantalla muestra el aviso de espera y sigue usable', function () {
    $e = escenarioAsistente();
    limitar(1);

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)
        ->set('mensaje', 'uno')->call('enviar')
        ->set('mensaje', 'dos')->call('enviar')->assertSee('Podrás volver a preguntar en')->assertSet('mensaje', '');
});
