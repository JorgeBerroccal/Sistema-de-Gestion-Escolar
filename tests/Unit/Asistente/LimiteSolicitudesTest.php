<?php

use App\Asistente\LimiteSolicitudes;
use App\Models\Usuario;

function usuarioNumero(int $id): Usuario
{
    return (new Usuario)->forceFill(['id' => $id]);
}

beforeEach(function () {
    config(['asistente.limites' => ['usuario_minuto' => 2, 'usuario_dia' => 5, 'global_minuto' => 100, 'global_dia' => 100]]);
});

it('permite hasta el límite por minuto y luego informa cuánto esperar', function () {
    $limite = new LimiteSolicitudes;
    $u = usuarioNumero(1);

    expect($limite->intentar($u))->toBeNull()->and($limite->intentar($u))->toBeNull();

    $espera = $limite->intentar($u);

    expect($espera)->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
});

it('reinicia la ventana del minuto al pasar el tiempo', function () {
    $limite = new LimiteSolicitudes;
    $u = usuarioNumero(1);
    $limite->intentar($u);
    $limite->intentar($u);
    expect($limite->intentar($u))->not->toBeNull();

    $this->travel(61)->seconds();

    expect($limite->intentar($u))->toBeNull();
});

it('aplica el límite por día aunque el del minuto se reinicie', function () {
    $limite = new LimiteSolicitudes;
    $u = usuarioNumero(1);

    foreach (range(1, 5) as $i) {
        expect($limite->intentar($u))->toBeNull();
        $this->travel(61)->seconds();
    }

    $espera = $limite->intentar($u);

    expect($espera)->toBeGreaterThan(60);
});

it('los usuarios son independientes', function () {
    $limite = new LimiteSolicitudes;
    $limite->intentar(usuarioNumero(1));
    $limite->intentar(usuarioNumero(1));

    expect($limite->intentar(usuarioNumero(1)))->not->toBeNull()->and($limite->intentar(usuarioNumero(2)))->toBeNull();
});

it('el límite global se aplica aunque cada usuario esté por debajo del suyo', function () {
    config(['asistente.limites' => ['usuario_minuto' => 10, 'usuario_dia' => 10, 'global_minuto' => 3, 'global_dia' => 100]]);
    $limite = new LimiteSolicitudes;

    expect($limite->intentar(usuarioNumero(1)))->toBeNull()->and($limite->intentar(usuarioNumero(2)))->toBeNull()
        ->and($limite->intentar(usuarioNumero(3)))->toBeNull()->and($limite->intentar(usuarioNumero(4)))->not->toBeNull();
});

it('un intento rechazado no consume cupo de los otros contadores', function () {
    config(['asistente.limites' => ['usuario_minuto' => 1, 'usuario_dia' => 10, 'global_minuto' => 2, 'global_dia' => 100]]);
    $limite = new LimiteSolicitudes;
    $limite->intentar(usuarioNumero(1));

    expect($limite->intentar(usuarioNumero(1)))->not->toBeNull()->and($limite->intentar(usuarioNumero(1)))->not->toBeNull()
        ->and($limite->intentar(usuarioNumero(2)))->toBeNull();
});

it('redacta la espera en segundos, minutos u horas', function () {
    expect(LimiteSolicitudes::textoEspera(1))->toBe('1 segundo')->and(LimiteSolicitudes::textoEspera(45))->toBe('45 segundos')
        ->and(LimiteSolicitudes::textoEspera(61))->toBe('2 minutos')->and(LimiteSolicitudes::textoEspera(60))->toBe('1 minuto')
        ->and(LimiteSolicitudes::textoEspera(3601))->toBe('2 horas');
});
