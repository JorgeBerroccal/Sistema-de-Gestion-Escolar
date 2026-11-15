<?php

namespace App\Asistente;

use App\Models\Alumno;

/** Resultado de `ResolverAlumno`: el alumno elegido, o un texto local (sin llamar al proveedor). */
final readonly class Resolucion
{
    private function __construct(
        public ?Alumno $alumno,
        public ?string $aclaracion,
        public ?string $sinDatos,
    ) {}

    public static function alumno(Alumno $alumno): self
    {
        return new self($alumno, null, null);
    }

    public static function aclaracion(string $texto): self
    {
        return new self(null, $texto, null);
    }

    public static function sinDatos(string $texto): self
    {
        return new self(null, null, $texto);
    }
}
