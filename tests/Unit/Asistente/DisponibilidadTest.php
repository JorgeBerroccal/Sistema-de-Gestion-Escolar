<?php

use App\Asistente\Disponibilidad;
use App\Asistente\Proveedores\ProveedorFalso;

function disponibilidad(string $entorno, bool $admiteDatosReales): ?string
{
    app()->detectEnvironment(fn () => $entorno);

    // El falso nunca admite datos reales en producción, así que se usa un adaptador que lo declara a mano.
    $proveedor = new class($admiteDatosReales) extends ProveedorFalso
    {
        public function __construct(private bool $admite) {}

        public function admiteDatosReales(): bool
        {
            return $this->admite;
        }
    };

    return (new Disponibilidad($proveedor))->verificar();
}

it('en producción niega el servicio si el adaptador no admite datos reales', function () {
    expect(disponibilidad('production', false))->toContain('deshabilitado')->toContain('datos reales');
});

it('en producción permite el servicio si el adaptador admite datos reales', function () {
    expect(disponibilidad('production', true))->toBeNull();
});

it('fuera de producción permite el servicio aunque el adaptador no admita datos reales', function (string $entorno) {
    expect(disponibilidad($entorno, false))->toBeNull();
})->with(['local', 'staging', 'testing']);
