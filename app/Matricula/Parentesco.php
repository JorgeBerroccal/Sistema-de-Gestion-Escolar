<?php

namespace App\Matricula;

/** Parentesco del apoderado con el alumno. */
enum Parentesco: string
{
    case Madre = 'madre';
    case Padre = 'padre';
    case Tutor = 'tutor';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return ucfirst($this->value);
    }
}
