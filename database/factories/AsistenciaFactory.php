<?php

namespace Database\Factories;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Seccion;
use App\Models\Usuario;
use Database\Seeders\EstadosAsistenciaSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asistencia> */
class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    public function definition(): array
    {
        return [
            'seccion_id' => Seccion::factory(),
            'alumno_id' => Alumno::factory(),
            'fecha' => now()->toDateString(),
            'estado_id' => fn () => $this->catalogo('presente')->id,
            'registrado_por' => Usuario::factory(),
        ];
    }

    public function catalogo(string $codigo): EstadoAsistencia
    {
        EstadoAsistencia::query()->exists() || (new EstadosAsistenciaSeeder)->run();

        return EstadoAsistencia::where('codigo', $codigo)->firstOrFail();
    }

    public function con(string $codigo): static
    {
        return $this->state(fn () => ['estado_id' => $this->catalogo($codigo)->id]);
    }
}
