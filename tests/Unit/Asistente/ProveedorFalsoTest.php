<?php

use App\Asistente\Proveedores\ProveedorFalso;
use App\Asistente\Proveedores\ProveedorNoDisponible;

it('responde de forma determinista en español y guarda lo recibido', function () {
    $falso = new ProveedorFalso;
    $mensajes = [
        ['rol' => 'system', 'texto' => 'instrucciones'],
        ['rol' => 'system', 'texto' => 'CONTEXTO: promedio 15,5'],
        ['rol' => 'user', 'texto' => '¿cómo voy?'],
    ];

    $a = $falso->responder($mensajes);
    $b = $falso->responder($mensajes);

    expect($a->texto)->toBe($b->texto)
        ->and($a->texto)->toContain('español')->toContain('promedio 15,5')->not->toContain('instrucciones')
        ->and($falso->llamadas())->toBe(2)
        ->and($falso->textoRecibido())->toContain('¿cómo voy?');
});

it('declara que admite datos reales y puede declarar lo contrario', function () {
    $falso = new ProveedorFalso;

    expect($falso->admiteDatosReales())->toBeTrue()
        ->and($falso->declararDatosReales(false)->admiteDatosReales())->toBeFalse()
        ->and($falso->nombre())->toBe('falso');
});

it('simula un proveedor caído', function () {
    $falso = (new ProveedorFalso)->fallar();

    expect(fn () => $falso->responder([['rol' => 'user', 'texto' => 'hola']]))->toThrow(ProveedorNoDisponible::class)
        ->and($falso->llamadas())->toBe(1);
});
