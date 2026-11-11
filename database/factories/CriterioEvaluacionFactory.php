<?php

namespace Database\Factories;

use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CriterioEvaluacion> */
class CriterioEvaluacionFactory extends Factory
{
    protected $model = CriterioEvaluacion::class;

    public function definition(): array
    {
        return [
            'seccion_id' => Seccion::factory(),
            'curso_id' => Curso::factory(),
            'periodo_id' => PeriodoEvaluacion::factory(),
            'nombre' => 'Criterio '.fake()->unique()->lexify('????????'),
            'peso' => 100,
            'orden' => 1,
        ];
    }
}
