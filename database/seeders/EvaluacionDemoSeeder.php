<?php

namespace Database\Seeders;

use App\Evaluacion\CalculoPromedios;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\EscalaCalificacion;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos de ejemplo del Módulo 5: criterios y notas FICTICIOS de los alumnos ficticios de `MatriculaDemoSeeder`
 * (documentos del rango reservado `9000xxxx`; Ley 29733), en el primer periodo del año vigente, con ese periodo
 * cerrado para la primera sección. No crea cuentas: el registrador es una cuenta que ya existe y puede gestionar
 * notas. Usa la escala de la institución (letras o números) con un patrón determinista. Idempotente. Rechaza
 * producción. `DatabaseSeeder` lo llama solo en local; en staging se ejecuta a mano con
 * `db:seed --class=EvaluacionDemoSeeder` después de `MatriculaDemoSeeder`.
 */
class EvaluacionDemoSeeder extends Seeder
{
    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    /** Criterios de ejemplo (nombre => peso): suman 100. */
    private const CRITERIOS = ['Práctica calificada' => 40, 'Examen' => 60];

    private const CURSOS_POR_SECCION = 3;

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'EvaluacionDemoSeeder solo puede ejecutarse en %s; el entorno actual es "%s". Los alumnos son datos personales de menores.',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->call([RolesYPermisosSeeder::class, EscalasCalificacionSeeder::class]);

        $vigente = AnioEscolar::where('vigente', 1)->first()
            ?? throw new RuntimeException('No hay un año escolar vigente: ejecuta antes AcademicoDemoSeeder.');
        $periodo = PeriodoEvaluacion::where('anio_escolar_id', $vigente->id)->orderBy('orden')->first()
            ?? throw new RuntimeException('El año vigente no tiene periodos de evaluación.');
        $registrador = Usuario::query()
            ->whereHas('rol.permisos', fn ($q) => $q->where('identificador', 'notas.gestionar'))
            ->where('activo', true)->orderBy('id')->first()
            ?? throw new RuntimeException('No hay una cuenta que pueda gestionar notas para registrarla como autora.');

        $matriculas = Matricula::query()->with('seccion.grado.cursos')->where('anio_id', $vigente->id)
            ->where('estado', 'matriculado')
            ->whereHas('alumno', fn ($q) => $q->where('numero_documento', 'like', MatriculaDemoSeeder::PREFIJO_DOCUMENTO.'%'))
            ->orderBy('id')->get();

        if ($matriculas->isEmpty()) {
            throw new RuntimeException('No hay matrículas de ejemplo: ejecuta antes MatriculaDemoSeeder.');
        }

        // La institución de ejemplo ya trae escala; si no, se asigna la literal solo para este ejemplo.
        $ficha = Institucion::actual();
        if (! $ficha->exists || $ficha->escala_calificacion_id === null) {
            throw new RuntimeException('La institución no tiene escala de calificación: ejecuta antes AcademicoDemoSeeder.');
        }

        $calculo = CalculoPromedios::deEscala(EscalaCalificacion::findOrFail($ficha->escala_calificacion_id));
        $valores = $calculo->esLiteral()
            ? array_keys($ficha->escala->equivalentes)
            : range((int) ($ficha->escala->valores['minimo'] ?? 0) + 8, (int) ($ficha->escala->valores['maximo'] ?? 20));
        $ahora = now();

        DB::transaction(function () use ($matriculas, $periodo, $registrador, $valores, $calculo, $ahora) {
            $criteriosPorPar = [];
            $secciones = [];
            $n = 0;

            foreach ($matriculas as $matricula) {
                $seccion = $matricula->seccion;
                $secciones[$seccion->id] = $seccion;

                foreach ($seccion->grado->cursos->sortBy('id')->take(self::CURSOS_POR_SECCION) as $c => $curso) {
                    $clave = $seccion->id.'-'.$curso->id;

                    if (! isset($criteriosPorPar[$clave])) {
                        $orden = 0;
                        $criteriosPorPar[$clave] = collect(self::CRITERIOS)->map(fn ($peso, $nombre) => CriterioEvaluacion::firstOrCreate(
                            ['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id, 'nombre' => $nombre],
                            ['peso' => $peso, 'orden' => ++$orden],
                        ))->values();
                    }

                    $filas = [];
                    foreach ($criteriosPorPar[$clave] as $k => $criterio) {
                        $valor = $valores[($n * 7 + $c * 3 + $k) % count($valores)];
                        $normal = $calculo->normalizar((string) $valor);
                        $filas[] = [
                            'criterio_id' => $criterio->id, 'alumno_id' => $matricula->alumno_id,
                            'valor' => $normal['valor'], 'valor_numerico' => $normal['numerico'],
                            'registrado_por' => $registrador->id, 'created_at' => $ahora, 'updated_at' => $ahora,
                        ];
                    }
                    Nota::insertOrIgnore($filas);
                }

                $n++;
            }

            // El periodo queda cerrado (publicado) solo en la primera sección: las demás siguen abiertas.
            $primera = collect($secciones)->first();
            CierrePeriodo::firstOrCreate(
                ['seccion_id' => $primera->id, 'periodo_id' => $periodo->id],
                ['cerrado_por' => $registrador->id, 'cerrado_en' => $ahora],
            );
        });
    }
}
