<?php

use Illuminate\Foundation\Events\DiagnosingHealth;

// Contrato: specs/002-despliegue-ci-staging/contracts/salud.md (FR-013).

it('responde 200 con JSON, estado ok y la versión desplegada', function () {
    config(['app.version' => 'v0.2.0']);

    $this->getJson('/up')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['status' => 'ok', 'version' => 'v0.2.0']);
});

it('toma la versión de SGE_VERSION y usa "dev" por defecto', function () {
    expect(config('app.version'))->toBe(env('SGE_VERSION', 'dev'));
});

it('es pública y no crea sesión ni cookies', function () {
    $respuesta = $this->get('/up');

    $respuesta->assertOk();
    expect($respuesta->headers->getCookies())->toBeEmpty();
});

it('dispara el evento DiagnosingHealth de Laravel', function () {
    Event::fake([DiagnosingHealth::class]);

    $this->get('/up')->assertOk();

    Event::assertDispatched(DiagnosingHealth::class);
});

it('no cambia las pantallas del Módulo 1: el login no muestra la versión', function () {
    config(['app.version' => 'v9.9.9']);

    $this->get('/login')->assertOk()->assertDontSee('v9.9.9');
});
