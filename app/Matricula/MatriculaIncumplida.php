<?php

namespace App\Matricula;

use RuntimeException;

/**
 * Una regla de matrícula no se cumple. El mensaje está en español, se muestra tal cual y NUNCA lleva
 * nombres ni documentos de alumnos (Ley 29733, FR-026): se identifica por posición en la lista elegida.
 */
class MatriculaIncumplida extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?int $posicion = null)
    {
        parent::__construct($mensaje);
    }
}
