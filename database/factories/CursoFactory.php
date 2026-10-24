<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Curso> */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'nivel_id' => Nivel::factory(),
            'nombre' => 'Curso '.fake()->unique()->lexify('????????'),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
