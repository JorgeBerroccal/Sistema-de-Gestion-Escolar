<?php

namespace Database\Factories;

use App\Models\Institucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Institucion> */
class InstitucionFactory extends Factory
{
    protected $model = Institucion::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Institución '.fake()->lexify('????????'),
            'codigo_modular' => fake()->numerify('#######'),
            'ugel_dre' => 'UGEL '.fake()->lexify('????'),
        ];
    }

    /** La ficha es única: su id está fijo en 1. */
    public function configure(): static
    {
        return $this->afterMaking(fn (Institucion $ficha) => $ficha->id = Institucion::ID);
    }
}
