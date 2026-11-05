<?php

namespace App\Matricula;

/** Sexo del alumno, guardado como una letra. */
enum Sexo: string
{
    case Femenino = 'F';
    case Masculino = 'M';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Femenino => 'Femenino',
            self::Masculino => 'Masculino',
        };
    }
}
