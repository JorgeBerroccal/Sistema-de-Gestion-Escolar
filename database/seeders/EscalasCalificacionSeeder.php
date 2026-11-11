<?php

namespace Database\Seeders;

use App\Models\EscalaCalificacion;
use Illuminate\Database\Seeder;

/**
 * Las dos escalas de calificación que ofrece el sistema (spec FR-003). Es carga base, no de ejemplo:
 * corre en todo entorno. Idempotente por `codigo`; no pisa cambios hechos después.
 *
 * Los equivalentes numéricos de la escala literal (AD=4, A=3, B=2, C=1) y los umbrales de aprobación son
 * **configuración de la institución**, un valor inicial razonable para poder promediar; no son una regla del
 * Currículo Nacional. Solo se completan si están vacíos: nunca pisan lo que el Administrador cambió.
 */
class EscalasCalificacionSeeder extends Seeder
{
    /** codigo => [nombre, valores, equivalentes, umbral_aprobacion] */
    public const ESCALAS = [
        'literal' => ['Literal', ['AD', 'A', 'B', 'C'], ['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1], 'B'],
        'vigesimal' => ['Vigesimal', ['minimo' => 0, 'maximo' => 20], null, '11'],
    ];

    public function run(): void
    {
        foreach (self::ESCALAS as $codigo => [$nombre, $valores, $equivalentes, $umbral]) {
            $escala = EscalaCalificacion::firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'valores' => $valores, 'equivalentes' => $equivalentes, 'umbral_aprobacion' => $umbral],
            );

            if ($escala->equivalentes === null && $equivalentes !== null) {
                $escala->equivalentes = $equivalentes;
            }
            if ($escala->umbral_aprobacion === null) {
                $escala->umbral_aprobacion = $umbral;
            }
            $escala->save();
        }
    }
}
