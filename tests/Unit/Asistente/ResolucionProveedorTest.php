<?php

use App\Asistente\Proveedores\ProveedorFalso;
use App\Asistente\Proveedores\ProveedorIA;

it('resuelve el adaptador falso por configuración y usa una sola instancia', function () {
    config(['asistente.proveedor' => 'falso']);
    app()->forgetInstance(ProveedorIA::class);

    expect(app(ProveedorIA::class))->toBeInstanceOf(ProveedorFalso::class)
        ->and(app(ProveedorIA::class))->toBe(app(ProveedorIA::class));
});

it('la suite usa el adaptador falso por defecto', function () {
    expect(config('asistente.proveedor'))->toBe('falso');
});

it('un proveedor desconocido es un error claro', function () {
    config(['asistente.proveedor' => 'inexistente']);
    app()->forgetInstance(ProveedorIA::class);

    expect(fn () => app(ProveedorIA::class))->toThrow(InvalidArgumentException::class, 'inexistente');
});
