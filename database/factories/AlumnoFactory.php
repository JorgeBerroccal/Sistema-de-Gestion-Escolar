<?php

namespace Database\Factories;

use App\Matricula\Sexo;
use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos SIEMPRE ficticios (Ley 29733): nombres inventados y DNI del rango reservado 9000xxxx.
 *
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    public function definition(): array
    {
        return [
            'nombres' => fake()->randomElement(['Alumno', 'Alumna']).' '.fake()->unique()->lexify('Prueba????'),
            'apellidos' => fake()->lexify('Ficticio???').' '.fake()->lexify('Ejemplo???'),
            'tipo_documento_id' => fn () => TipoDocumentoFactory::dni()->id,
            'numero_documento' => (string) fake()->unique()->numberBetween(90000000, 99999999),
            'fecha_nacimiento' => fake()->dateTimeBetween('-17 years', '-6 years')->format('Y-m-d'),
            'sexo' => fake()->randomElement(Sexo::cases()),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
