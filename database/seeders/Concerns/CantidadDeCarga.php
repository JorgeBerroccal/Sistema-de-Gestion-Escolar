<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Cuántas cuentas de familia `carga0001…` siembra la prueba de carga: `CARGA_TOTAL` (por defecto 1000, la carga de los
 * Módulos 1 y 5; 2000 en la sesión adicional de la enmienda 2.4.0 de la constitución). Se lee también con `env()`
 * por la misma razón que la contraseña: los seeders corren una sola vez con `exec -e CARGA_TOTAL=…`.
 */
trait CantidadDeCarga
{
    public const CARGA_POR_DEFECTO = 1000;

    public const CARGA_MAXIMA = 5000;

    protected function cantidadDeCarga(): int
    {
        $valor = config('sge.carga.total');
        $valor = blank($valor) ? env('CARGA_TOTAL') : $valor; // `?:` descartaría "0", que debe rechazarse

        if (blank($valor)) {
            return self::CARGA_POR_DEFECTO;
        }

        if (! ctype_digit((string) $valor) || (int) $valor < 1 || (int) $valor > self::CARGA_MAXIMA) {
            throw new RuntimeException(sprintf('CARGA_TOTAL debe ser un entero entre 1 y %d (recibido "%s").', self::CARGA_MAXIMA, $valor));
        }

        return (int) $valor;
    }
}
