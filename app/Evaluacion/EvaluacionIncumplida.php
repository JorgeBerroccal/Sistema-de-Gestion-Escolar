<?php

namespace App\Evaluacion;

use RuntimeException;

/**
 * Una regla de evaluación no se cumple. El mensaje se muestra tal cual y NUNCA lleva nombres ni documentos de
 * alumnos (Ley 29733): se identifica por posición (fila/columna) o por #id. Cuando hay varios problemas
 * (celdas inválidas de una tabla) se listan en `errores`.
 */
class EvaluacionIncumplida extends RuntimeException
{
    /** @param  list<string>  $errores */
    public function __construct(string $message, public readonly array $errores = [])
    {
        parent::__construct($message);
    }

    /** Mensajes para mostrar: la lista de errores si la hay, si no el mensaje. @return list<string> */
    public function mensajes(): array
    {
        return $this->errores !== [] ? $this->errores : [$this->getMessage()];
    }
}
