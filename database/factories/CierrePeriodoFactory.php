<?php

namespace Database\Factories;

use App\Models\CierrePeriodo;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CierrePeriodo> */
class CierrePeriodoFactory extends Factory
{
    protected $model = CierrePeriodo::class;

    public function definition(): array
    {
        return [
            'seccion_id' => Seccion::factory(),
            'periodo_id' => PeriodoEvaluacion::factory(),
            'cerrado_por' => Usuario::factory(),
            'cerrado_en' => now(),
        ];
    }
}
