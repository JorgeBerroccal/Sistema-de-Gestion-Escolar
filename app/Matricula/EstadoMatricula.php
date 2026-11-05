<?php

namespace App\Matricula;

/** Estado de una matrícula; solo «matriculado» cuenta como activa. */
enum EstadoMatricula: string
{
    case Matriculado = 'matriculado';
    case Retirado = 'retirado';
    case Trasladado = 'trasladado';

    public function etiqueta(): string
    {
        return ucfirst($this->value);
    }

    public function esActiva(): bool
    {
        return $this === self::Matriculado;
    }
}
