<?php

namespace Database\Seeders;

use App\Models\AnioEscolar;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Matricula;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de ejemplo del Módulo 4: asistencia FICTICIA de los alumnos ficticios de `MatriculaDemoSeeder` (documentos
 * del rango reservado `9000xxxx`; Ley 29733). Registra los últimos días hábiles del año vigente con un patrón
 * determinista. No crea cuentas: el registrador es una cuenta que ya existe y puede gestionar la asistencia.
 * Idempotente. Rechaza producción. `DatabaseSeeder` lo llama solo en local; en staging se ejecuta a mano con
 * `db:seed --class=AsistenciaDemoSeeder` después de `MatriculaDemoSeeder`.
 */
class AsistenciaDemoSeeder extends Seeder
{
    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    private const DIAS_HABILES = 15;

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'AsistenciaDemoSeeder solo puede ejecutarse en %s; el entorno actual es "%s". Los alumnos son datos personales de menores.',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->call([RolesYPermisosSeeder::class, EstadosAsistenciaSeeder::class]);

        $vigente = AnioEscolar::where('vigente', 1)->first()
            ?? throw new RuntimeException('No hay un año escolar vigente: ejecuta antes AcademicoDemoSeeder.');
        $registrador = Usuario::query()
            ->whereHas('rol.permisos', fn ($q) => $q->where('identificador', 'asistencia.gestionar'))
            ->where('activo', true)->orderBy('id')->first()
            ?? throw new RuntimeException('No hay una cuenta que pueda gestionar asistencia para registrarla como autora.');

        $matriculas = Matricula::query()->where('anio_id', $vigente->id)
            ->whereHas('alumno', fn ($q) => $q->where('numero_documento', 'like', MatriculaDemoSeeder::PREFIJO_DOCUMENTO.'%'))
            ->orderBy('id')->get();

        if ($matriculas->isEmpty()) {
            throw new RuntimeException('No hay matrículas de ejemplo: ejecuta antes MatriculaDemoSeeder.');
        }

        $estados = EstadoAsistencia::pluck('id', 'codigo');
        $dias = $this->diasHabiles($vigente);
        $ahora = now();
        $filas = [];

        foreach ($matriculas as $n => $matricula) {
            foreach ($dias as $i => $dia) {
                $dia = $dia->toDateString();

                // Solo mientras el alumno estaba en la sección (el retiro excluye desde su fecha).
                if ($dia < $matricula->fecha_matricula->toDateString() || ($matricula->fecha_estado && $dia >= $matricula->fecha_estado->toDateString())) {
                    continue;
                }

                $filas[] = [
                    'seccion_id' => $matricula->seccion_id,
                    'alumno_id' => $matricula->alumno_id,
                    'fecha' => $dia,
                    'estado_id' => $estados[$this->codigoDe($n, $i)],
                    'observacion' => null,
                    'motivo_justificacion' => $this->codigoDe($n, $i) === 'falta_justificada' ? 'Motivo de ejemplo' : null,
                    'registrado_por' => $registrador->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        // Idempotente: lo ya registrado (también si se corrigió a mano) se conserva.
        foreach (array_chunk($filas, 500) as $bloque) {
            Asistencia::insertOrIgnore($bloque);
        }
    }

    private function codigoDe(int $alumno, int $dia): string
    {
        return match (true) {
            ($alumno + $dia) % 13 === 0 => 'falta',
            ($alumno * 3 + $dia) % 17 === 0 => 'falta_justificada',
            ($alumno + $dia * 2) % 7 === 0 => 'tarde',
            default => 'presente',
        };
    }

    /** @return list<CarbonImmutable> los últimos días hábiles hasta hoy dentro del año, del más antiguo al más reciente */
    private function diasHabiles(AnioEscolar $anio): array
    {
        $dia = CarbonImmutable::parse(now(config('asistencia.zona_horaria'))->toDateString());
        $inicio = CarbonImmutable::parse($anio->fecha_inicio->toDateString());
        $fin = CarbonImmutable::parse($anio->fecha_fin->toDateString());
        $dias = [];

        while (count($dias) < self::DIAS_HABILES && $dia >= $inicio) {
            if (! $dia->isWeekend() && $dia <= $fin) {
                $dias[] = $dia;
            }
            $dia = $dia->subDay();
        }

        return array_reverse($dias);
    }
}
