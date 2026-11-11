<?php

namespace Database\Factories;

use App\Models\Alumno;
use App\Models\CriterioEvaluacion;
use App\Models\Nota;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Nota> */
class NotaFactory extends Factory
{
    protected $model = Nota::class;

    public function definition(): array
    {
        return [
            'criterio_id' => CriterioEvaluacion::factory(),
            'alumno_id' => Alumno::factory(),
            'valor' => '15',
            'valor_numerico' => 15,
            'registrado_por' => Usuario::factory(),
        ];
    }
}
