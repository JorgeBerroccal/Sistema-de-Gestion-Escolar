<?php

namespace Database\Factories;

use App\Academico\TipoPeriodo;
use App\Models\AnioEscolar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnioEscolar> */
class AnioEscolarFactory extends Factory
{
    protected $model = AnioEscolar::class;

    public function definition(): array
    {
        $anio = fake()->unique()->numberBetween(2000, 2999);

        return [
            'nombre' => (string) $anio,
            'fecha_inicio' => "{$anio}-03-01",
            'fecha_fin' => "{$anio}-12-20",
            'tipo_periodo' => TipoPeriodo::Bimestre,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function vigente(): static
    {
        return $this->afterCreating(fn (AnioEscolar $anio) => $anio->forceFill(['vigente' => 1])->save());
    }

    /** Crea además sus periodos válidos (4 bimestres o 3 trimestres) repartidos dentro del año. */
    public function conPeriodos(): static
    {
        return $this->afterCreating(function (AnioEscolar $anio) {
            $n = $anio->tipo_periodo->cantidadPeriodos();
            $inicio = $anio->fecha_inicio->copy();
            $dias = intdiv((int) $anio->fecha_inicio->diffInDays($anio->fecha_fin), $n);

            foreach ($anio->tipo_periodo->nombresSugeridos() as $orden => $nombre) {
                $desde = $inicio->copy()->addDays(($orden - 1) * $dias);
                $anio->periodos()->create([
                    'orden' => $orden,
                    'nombre' => $nombre,
                    'fecha_inicio' => $desde,
                    'fecha_fin' => $desde->copy()->addDays($dias - 2),
                ]);
            }
        });
    }
}
