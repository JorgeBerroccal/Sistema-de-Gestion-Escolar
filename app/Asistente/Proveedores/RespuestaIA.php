<?php

namespace App\Asistente\Proveedores;

/** Texto devuelto por el proveedor y el modelo que lo generó. */
final readonly class RespuestaIA
{
    public function __construct(
        public string $texto,
        public ?string $modelo = null,
    ) {}
}
