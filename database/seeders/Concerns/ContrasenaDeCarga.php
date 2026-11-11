<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Contraseña de las cuentas de la prueba de carga (`carga*`, `cargadoc*`). No está en el repositorio: se lee de
 * `CARGA_PASSWORD` y es obligatoria fuera de local y testing, donde solo se usa `password` por comodidad. Se lee
 * también con `env()` porque estos seeders corren una sola vez con `exec -e CARGA_PASSWORD=…` y, con la
 * configuración en caché (producción), `config()` ya no vería una variable definida después.
 */
trait ContrasenaDeCarga
{
    public const LONGITUD_MINIMA_CONTRASENA = 12;

    protected function contrasenaDeCarga(): string
    {
        $valor = config('sge.carga.password') ?: env('CARGA_PASSWORD');
        $conocida = app()->environment(['local', 'testing']);

        if (blank($valor)) {
            if ($conocida) {
                return 'password';
            }

            throw new RuntimeException('Falta CARGA_PASSWORD: define la contraseña de las cuentas de carga en el entorno (mínimo '.self::LONGITUD_MINIMA_CONTRASENA.' caracteres); no se escribe en el repositorio.');
        }

        if (! $conocida && mb_strlen((string) $valor) < self::LONGITUD_MINIMA_CONTRASENA) {
            throw new RuntimeException('CARGA_PASSWORD debe tener al menos '.self::LONGITUD_MINIMA_CONTRASENA.' caracteres.');
        }

        return (string) $valor;
    }
}
