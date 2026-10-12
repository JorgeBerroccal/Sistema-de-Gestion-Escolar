<?php

use App\Http\Requests\Auth\LoginRequest;

it('elige la columna según el login', function () {
    expect(LoginRequest::columnaPara('ana@colegio.pe'))->toBe('email')
        ->and(LoginRequest::columnaPara('ana.perez'))->toBe('username');
});

it('construye la clave del limitador normalizada', function () {
    expect(LoginRequest::claveLimite('  Ana.Perez ', '10.0.0.1'))->toBe('login|ana.perez|10.0.0.1');
});

it('produce la misma clave sin importar mayúsculas', function () {
    expect(LoginRequest::claveLimite('ANA.PEREZ', '10.0.0.1'))
        ->toBe(LoginRequest::claveLimite('ana.perez', '10.0.0.1'));
});

it('produce claves distintas para IPs distintas', function () {
    expect(LoginRequest::claveLimite('ana', '10.0.0.1'))->not->toBe(LoginRequest::claveLimite('ana', '10.0.0.2'));
});
