<?php

namespace App\Autorizacion\Pertenencia;

use InvalidArgumentException;

/** Resuelve la regla de pertenencia de un modelo desde config('pertenencia.reglas'). */
class RegistroReglasPertenencia
{
    public function reglaPara(string $modelo): ?ReglaPertenencia
    {
        $clase = config('pertenencia.reglas', [])[$modelo] ?? null;

        if ($clase === null) {
            return null;
        }

        $regla = app($clase);

        if (! $regla instanceof ReglaPertenencia) {
            throw new InvalidArgumentException("{$clase} debe implementar ".ReglaPertenencia::class.'.');
        }

        return $regla;
    }
}
