<?php

namespace Database\Seeders;

use App\Models\EscalaCalificacion;
use Illuminate\Database\Seeder;

/**
 * Las dos escalas de calificación que ofrece el sistema (spec FR-003). Es carga base, no de ejemplo:
 * corre en todo entorno. Idempotente por `codigo`; no pisa cambios hechos después.
 */
class EscalasCalificacionSeeder extends Seeder
{
    /** codigo => [nombre, valores] */
    public const ESCALAS = [
        'literal' => ['Literal', ['AD', 'A', 'B', 'C']],
        'vigesimal' => ['Vigesimal', ['minimo' => 0, 'maximo' => 20]],
    ];

    public function run(): void
    {
        foreach (self::ESCALAS as $codigo => [$nombre, $valores]) {
            EscalaCalificacion::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'valores' => $valores]);
        }
    }
}
