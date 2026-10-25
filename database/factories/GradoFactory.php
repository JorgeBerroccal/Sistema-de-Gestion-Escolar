<?php

namespace Database\Factories;

use App\Models\Grado;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grado> */
class GradoFactory extends Factory
{
    protected $model = Grado::class;

    public function definition(): array
    {
        return [
            'nivel_id' => Nivel::factory(),
            'nombre' => 'Grado '.fake()->unique()->lexify('????????'),
            'orden' => fake()->unique()->numberBetween(1, 60000),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
