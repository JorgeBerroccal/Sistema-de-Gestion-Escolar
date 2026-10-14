<?php

namespace App\Autorizacion\Pertenencia;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Un modelo con pertenencia sin regla registrada se deniega siempre (fail-closed, HTTP 403). */
class ReglaPertenenciaFaltante extends AccessDeniedHttpException
{
    public function __construct(string $modelo)
    {
        parent::__construct("No hay regla de pertenencia registrada para {$modelo}; acceso denegado por defecto.");
    }
}
