<?php

namespace Database\Factories;

use App\Matricula\EstadoMatricula;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\Seccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Matricula> */
class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    public function definition(): array
    {
        return [
            'alumno_id' => Alumno::factory(),
            'seccion_id' => Seccion::factory(),
            'anio_id' => fn (array $atributos) => Seccion::findOrFail($atributos['seccion_id'])->anio_escolar_id,
            'fecha_matricula' => now()->toDateString(),
            'estado' => EstadoMatricula::Matriculado,
        ];
    }

    public function retirada(): static
    {
        return $this->state(fn () => ['estado' => EstadoMatricula::Retirado, 'fecha_estado' => now()->toDateString()]);
    }
}
