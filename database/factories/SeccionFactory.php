<?php

namespace Database\Factories;

use App\Models\AnioEscolar;
use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Seccion> */
class SeccionFactory extends Factory
{
    protected $model = Seccion::class;

    public function definition(): array
    {
        return [
            'grado_id' => Grado::factory(),
            'anio_escolar_id' => AnioEscolar::factory(),
            'identificador' => fake()->unique()->lexify('????'),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
