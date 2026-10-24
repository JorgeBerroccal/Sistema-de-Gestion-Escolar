<?php

namespace Database\Factories;

use App\Models\AnioEscolar;
use App\Models\PeriodoEvaluacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeriodoEvaluacion> */
class PeriodoEvaluacionFactory extends Factory
{
    protected $model = PeriodoEvaluacion::class;

    public function definition(): array
    {
        return [
            'anio_escolar_id' => AnioEscolar::factory(),
            'orden' => 1,
            'nombre' => 'I Bimestre',
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-05-15',
        ];
    }
}
