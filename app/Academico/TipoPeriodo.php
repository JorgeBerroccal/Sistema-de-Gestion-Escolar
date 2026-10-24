<?php

namespace App\Academico;

/** Tipo de periodo de evaluación de un año escolar (la spec admite solo estos dos). */
enum TipoPeriodo: string
{
    case Bimestre = 'bimestre';
    case Trimestre = 'trimestre';

    private const ROMANOS = ['I', 'II', 'III', 'IV'];

    public function cantidadPeriodos(): int
    {
        return match ($this) {
            self::Bimestre => 4,
            self::Trimestre => 3,
        };
    }

    public function etiqueta(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, string> nombres propuestos, indexados desde 1: «I Bimestre», «II Bimestre»… */
    public function nombresSugeridos(): array
    {
        $nombres = [];

        for ($orden = 1; $orden <= $this->cantidadPeriodos(); $orden++) {
            $nombres[$orden] = self::ROMANOS[$orden - 1].' '.$this->etiqueta();
        }

        return $nombres;
    }
}
