<?php

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TipoDocumento> */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('T????'),
            'nombre' => 'Tipo de prueba',
            'longitud_min' => 4,
            'longitud_max' => 20,
            'solo_digitos' => false,
            'activo' => true,
        ];
    }

    /** DNI: exactamente 8 dígitos. Reusa el registro si ya existe. */
    public static function dni(): TipoDocumento
    {
        return TipoDocumento::firstOrCreate(['codigo' => 'DNI'], ['nombre' => 'DNI', 'longitud_min' => 8, 'longitud_max' => 8, 'solo_digitos' => true]);
    }
}
