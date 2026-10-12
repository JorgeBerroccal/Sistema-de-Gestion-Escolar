<?php

namespace Database\Factories;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permiso> */
class PermisoFactory extends Factory
{
    protected $model = Permiso::class;

    public function definition(): array
    {
        return [
            'identificador' => 'prueba.'.fake()->unique()->lexify('??????'),
            'nombre' => fake()->words(2, true),
            'descripcion' => fake()->sentence(),
        ];
    }
}
