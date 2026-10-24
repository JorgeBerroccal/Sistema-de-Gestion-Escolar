<?php

namespace App\Academico;

use RuntimeException;

/** Una regla de la gestión académica no se cumple; el mensaje está en español y se muestra tal cual. */
class ReglaAcademicaIncumplida extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?int $cursoId = null)
    {
        parent::__construct($mensaje);
    }
}
