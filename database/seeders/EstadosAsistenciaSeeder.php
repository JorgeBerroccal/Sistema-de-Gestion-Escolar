<?php

namespace Database\Seeders;

use App\Models\EstadoAsistencia;
use Illuminate\Database\Seeder;

/** Estados base de asistencia (idempotente). Forma parte de `sge:sincronizar-base`. */
class EstadosAsistenciaSeeder extends Seeder
{
    /** @var array<string, array{nombre: string, asistencia: bool, falta: bool, orden: int}> */
    public const ESTADOS = [
        'presente' => ['nombre' => 'Presente', 'asistencia' => true, 'falta' => false, 'orden' => 1],
        'tarde' => ['nombre' => 'Tarde', 'asistencia' => true, 'falta' => false, 'orden' => 2],
        'falta' => ['nombre' => 'Falta', 'asistencia' => false, 'falta' => true, 'orden' => 3],
        'falta_justificada' => ['nombre' => 'Falta justificada', 'asistencia' => false, 'falta' => false, 'orden' => 4],
    ];

    public function run(): void
    {
        foreach (self::ESTADOS as $codigo => $e) {
            EstadoAsistencia::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $e['nombre'],
                'cuenta_asistencia' => $e['asistencia'],
                'cuenta_falta' => $e['falta'],
                'orden' => $e['orden'],
            ]);
        }

        $falta = EstadoAsistencia::where('codigo', 'falta')->firstOrFail();
        $falta->update(['justificado_id' => EstadoAsistencia::where('codigo', 'falta_justificada')->value('id')]);
    }
}
