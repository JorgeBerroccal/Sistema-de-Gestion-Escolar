<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

/**
 * Tipos de documento de identidad y su regla de formato (FR-002). Es carga base, no de ejemplo: corre en
 * todo entorno. Idempotente por `codigo`; no pisa cambios hechos después.
 */
class TiposDocumentoSeeder extends Seeder
{
    /** codigo => [nombre, longitud mínima, longitud máxima, solo dígitos] */
    public const TIPOS = [
        'DNI' => ['DNI', 8, 8, true],
        'CE' => ['Carné de extranjería', 9, 12, false],
        'OTRO' => ['Otro documento', 4, 20, false],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $codigo => [$nombre, $min, $max, $soloDigitos]) {
            TipoDocumento::firstOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre, 'longitud_min' => $min, 'longitud_max' => $max, 'solo_digitos' => $soloDigitos,
            ]);
        }
    }
}
