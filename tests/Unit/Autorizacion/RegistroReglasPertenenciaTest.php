<?php

use App\Autorizacion\Pertenencia\RegistroReglasPertenencia;
use App\Autorizacion\Pertenencia\ReglaPertenenciaFaltante;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\Pertenencia\ReglaDePrueba;
use Tests\Support\Pertenencia\RegistroDePrueba;

it('devuelve la regla configurada', function () {
    config(['pertenencia.reglas' => [RegistroDePrueba::class => ReglaDePrueba::class]]);

    expect(app(RegistroReglasPertenencia::class)->reglaPara(RegistroDePrueba::class))->toBeInstanceOf(ReglaDePrueba::class);
});

it('devuelve null si no hay regla', function () {
    config(['pertenencia.reglas' => []]);

    expect(app(RegistroReglasPertenencia::class)->reglaPara(RegistroDePrueba::class))->toBeNull();
});

it('lanza InvalidArgumentException si la clase no implementa ReglaPertenencia', function () {
    config(['pertenencia.reglas' => [RegistroDePrueba::class => stdClass::class]]);

    app(RegistroReglasPertenencia::class)->reglaPara(RegistroDePrueba::class);
})->throws(InvalidArgumentException::class);

it('ReglaPertenenciaFaltante es un 403', function () {
    expect(new ReglaPertenenciaFaltante('X'))->toBeInstanceOf(AccessDeniedHttpException::class);
});
