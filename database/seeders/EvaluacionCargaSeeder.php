<?php

namespace Database\Seeders;

use App\Evaluacion\CalculoPromedios;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Database\Seeders\Concerns\ContrasenaDeCarga;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Datos de la sesión única de carga k6 del Módulo 5 (contracts/prueba-de-carga.md): 1020 alumnos FICTICIOS en 34
 * secciones `K01…K34` de un mismo grado (documentos `9001xxxx`; Ley 29733), con notas del primer periodo cerrado
 * (publicado); las cuentas `carga0001…1000` quedan vinculadas a un alumno y pueden ver notas y boletas; y 20
 * cuentas `cargadoc01…20` (Docente de carga) asignadas a las 20 primeras secciones, con los criterios del segundo
 * periodo —que queda abierto— para registrar notas. La contraseña sale de `CARGA_PASSWORD` (obligatoria fuera de local y
 * testing; no está en el repositorio): solo local, staging y testing, nunca producción (FR-017), y fuera de `DatabaseSeeder`. Las cuentas `carga*` se desactivan al terminar
 * la sesión. Idempotente. Requiere `AcademicoDemoSeeder` (año vigente con dos periodos y un grado).
 */
class EvaluacionCargaSeeder extends Seeder
{
    use ContrasenaDeCarga;

    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    public const SECCIONES = 34;

    public const ALUMNOS_POR_SECCION = 30;

    public const DOCENTES = 20;

    public const CRITERIOS = 5;

    /** Documentos ficticios de la carga: distintos del rango `9000` de los ejemplos. */
    public const PREFIJO_DOCUMENTO = '9001';

    public const CURSO = 'Curso de carga';

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'EvaluacionCargaSeeder solo puede ejecutarse en %s; el entorno actual es "%s". Crea cuentas con contraseña conocida y alumnos ficticios.',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->contrasenaDeCarga(); // falla antes de sembrar nada si falta en staging

        $this->call([RolesYPermisosSeeder::class, EscalasCalificacionSeeder::class, TiposDocumentoSeeder::class, LoadTestSeeder::class]);

        $vigente = AnioEscolar::where('vigente', 1)->first()
            ?? throw new RuntimeException('No hay un año escolar vigente: ejecuta antes AcademicoDemoSeeder.');
        $periodos = PeriodoEvaluacion::where('anio_escolar_id', $vigente->id)->orderBy('orden')->limit(2)->get();
        $grado = Grado::query()->orderBy('id')->first();

        if ($periodos->count() < 2 || ! $grado) {
            throw new RuntimeException('Hacen falta un grado y dos periodos en el año vigente: ejecuta antes AcademicoDemoSeeder.');
        }

        [$publicado, $abierto] = [$periodos[0], $periodos[1]];

        DB::transaction(function () use ($vigente, $grado, $publicado, $abierto) {
            $roles = $this->roles();
            $docentes = $this->docentes($roles['docente']);
            $curso = Curso::firstOrCreate(['nivel_id' => $grado->nivel_id, 'nombre' => self::CURSO], ['activo' => true]);
            $grado->cursos()->syncWithoutDetaching([$curso->id]);
            $secciones = $this->secciones($grado, $vigente);
            $alumnos = $this->alumnos($secciones, $vigente);
            $this->vincularFamilias($alumnos, $roles['carga']);
            $this->criteriosYNotas($secciones, $curso, $alumnos, $publicado, $docentes);
            $this->periodoAbierto($secciones, $curso, $abierto, $docentes);
        });
    }

    /** @return array{carga: Rol, docente: Rol} */
    private function roles(): array
    {
        $carga = Rol::where('nombre', 'Carga')->firstOrFail();
        $carga->permisos()->syncWithoutDetaching(Permiso::whereIn('identificador', ['notas.ver_propia', 'boletas.ver_propia', 'matricula.ser_alumno'])->pluck('id'));

        $docente = Rol::firstOrCreate(['nombre' => 'Carga docente'], ['descripcion' => 'Docente de la prueba de carga.']);
        $docente->permisos()->syncWithoutDetaching(Permiso::whereIn('identificador', ['notas.registrar_propia', 'docencia.dictar'])->pluck('id'));

        return ['carga' => $carga, 'docente' => $docente];
    }

    /** @return Collection<int, Usuario> `cargadoc01…20` */
    private function docentes(Rol $rol)
    {
        $hash = Hash::make($this->contrasenaDeCarga());
        $ahora = now();
        $filas = [];

        foreach (range(1, self::DOCENTES) as $i) {
            $filas[] = [
                'nombres' => 'Docente', 'apellidos' => sprintf('Carga %02d', $i), 'username' => sprintf('cargadoc%02d', $i),
                'email' => null, 'password' => $hash, 'rol_id' => $rol->id, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
            ];
        }

        DB::table('usuarios')->upsert($filas, ['username'], ['password', 'rol_id', 'activo', 'updated_at']);

        return Usuario::where('username', 'like', 'cargadoc%')->orderBy('username')->get();
    }

    /** @return Collection<int, Seccion> `K01…K34` */
    private function secciones(Grado $grado, AnioEscolar $vigente)
    {
        return collect(range(1, self::SECCIONES))->map(fn (int $i) => Seccion::firstOrCreate(
            ['grado_id' => $grado->id, 'anio_escolar_id' => $vigente->id, 'identificador' => sprintf('K%02d', $i)],
            ['activo' => true],
        ));
    }

    /**
     * Alumnos ficticios y su matrícula: 30 por sección, en orden, de modo que el alumno N va a la sección ceil(N/30).
     *
     * @return Collection<int, Alumno> indexada por el número del alumno (1…1020)
     */
    private function alumnos($secciones, AnioEscolar $vigente)
    {
        $dni = TipoDocumento::where('codigo', 'DNI')->firstOrFail();
        $total = self::SECCIONES * self::ALUMNOS_POR_SECCION;
        $documentos = fn (int $n) => self::PREFIJO_DOCUMENTO.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        $ahora = now();

        $existentes = Alumno::where('tipo_documento_id', $dni->id)->where('numero_documento', 'like', self::PREFIJO_DOCUMENTO.'%')->pluck('id', 'numero_documento');
        $nuevos = [];

        foreach (range(1, $total) as $n) {
            if (! isset($existentes[$documentos($n)])) {
                $nuevos[] = [
                    'nombres' => 'Alumno', 'apellidos' => sprintf('Carga %04d', $n), 'tipo_documento_id' => $dni->id,
                    'numero_documento' => $documentos($n), 'fecha_nacimiento' => (now()->year - 10).'-06-15', 'sexo' => $n % 2 === 0 ? 'F' : 'M',
                    'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                ];
            }
        }

        foreach (array_chunk($nuevos, 500) as $lote) {
            Alumno::insert($lote);
        }

        $alumnos = Alumno::where('tipo_documento_id', $dni->id)->where('numero_documento', 'like', self::PREFIJO_DOCUMENTO.'%')->orderBy('numero_documento')->get()->values();
        $yaMatriculados = Matricula::where('anio_id', $vigente->id)->whereIn('alumno_id', $alumnos->pluck('id'))->pluck('alumno_id')->flip();
        $matriculas = [];

        foreach ($alumnos as $i => $alumno) {
            if (! isset($yaMatriculados[$alumno->id])) {
                $matriculas[] = [
                    'alumno_id' => $alumno->id, 'seccion_id' => $secciones[intdiv($i, self::ALUMNOS_POR_SECCION)]->id, 'anio_id' => $vigente->id,
                    'fecha_matricula' => $vigente->fecha_inicio->toDateString(), 'estado' => 'matriculado', 'created_at' => $ahora, 'updated_at' => $ahora,
                ];
            }
        }

        foreach (array_chunk($matriculas, 500) as $lote) {
            DB::table('matriculas')->insert($lote);
        }

        return $alumnos;
    }

    /** `carga0001…1000` ← alumno 1…1000: un usuario por ficha (la columna es única). */
    private function vincularFamilias($alumnos, Rol $carga): void
    {
        $usuarios = Usuario::where('rol_id', $carga->id)->where('username', 'like', 'carga%')->orderBy('username')->pluck('id');

        foreach ($usuarios as $i => $usuarioId) {
            $alumno = $alumnos[$i] ?? null;

            if ($alumno && $alumno->usuario_id === null) {
                Alumno::whereKey($alumno->id)->whereNull('usuario_id')->update(['usuario_id' => $usuarioId]);
            }
        }
    }

    /** Primer periodo: 5 criterios y una nota por criterio y alumno en cada sección, cerrado (publicado). */
    private function criteriosYNotas($secciones, Curso $curso, $alumnos, PeriodoEvaluacion $periodo, $docentes): void
    {
        $ahora = now();
        $registrador = $docentes->first();

        // Las notas deben ser válidas en la escala de la institución (letras o números).
        $escala = Institucion::actual()->escala
            ?? throw new RuntimeException('La institución no tiene escala de calificación: ejecuta antes AcademicoDemoSeeder.');
        $calculo = CalculoPromedios::deEscala($escala);
        $valores = $calculo->esLiteral() ? array_keys($escala->equivalentes) : range((int) ($escala->valores['minimo'] ?? 0) + 8, (int) ($escala->valores['maximo'] ?? 20));

        foreach ($secciones as $s => $seccion) {
            $criterios = $this->criterios($seccion, $curso, $periodo);
            $filas = [];

            foreach ($alumnos->slice($s * self::ALUMNOS_POR_SECCION, self::ALUMNOS_POR_SECCION) as $a => $alumno) {
                foreach ($criterios as $k => $criterio) {
                    $nota = $calculo->normalizar((string) $valores[($a * 7 + $k * 3) % count($valores)]);
                    $filas[] = [
                        'criterio_id' => $criterio->id, 'alumno_id' => $alumno->id, 'valor' => $nota['valor'], 'valor_numerico' => $nota['numerico'],
                        'registrado_por' => $registrador->id, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ];
                }
            }

            Nota::insertOrIgnore($filas);
            CierrePeriodo::firstOrCreate(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id], ['cerrado_por' => $registrador->id, 'cerrado_en' => $ahora]);
        }
    }

    /** Segundo periodo, abierto: asignaciones de los 20 docentes y los 5 criterios de sus secciones, sin notas. */
    private function periodoAbierto($secciones, Curso $curso, PeriodoEvaluacion $periodo, $docentes): void
    {
        foreach ($docentes as $i => $docente) {
            $seccion = $secciones[$i];
            AsignacionDocente::firstOrCreate(['seccion_id' => $seccion->id, 'curso_id' => $curso->id], ['usuario_id' => $docente->id]);
            $this->criterios($seccion, $curso, $periodo);
        }
    }

    /** @return Collection<int, CriterioEvaluacion> */
    private function criterios(Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo)
    {
        return collect(range(1, self::CRITERIOS))->map(fn (int $k) => CriterioEvaluacion::firstOrCreate(
            ['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id, 'nombre' => "Criterio {$k}"],
            ['peso' => 100 / self::CRITERIOS, 'orden' => $k],
        ));
    }
}
