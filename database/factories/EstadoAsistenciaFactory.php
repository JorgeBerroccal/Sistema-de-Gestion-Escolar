<?php

namespace Database\Factories;

use App\Models\EstadoAsistencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EstadoAsistencia> */
class EstadoAsistenciaFactory extends Factory
{
    protected $model = EstadoAsistencia::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('estado_????'),
            'nombre' => fake()->unique()->lexify('Estado ????'),
            'cuenta_asistencia' => true,
            'cuenta_falta' => false,
            'orden' => 1,
            'activo' => true,
        ];
    }
}
