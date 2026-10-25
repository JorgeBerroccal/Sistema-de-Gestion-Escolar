<?php

namespace Database\Seeders;

use App\Academico\AnioEscolarService;
use App\Academico\TipoPeriodo;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\EscalaCalificacion;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Nivel;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos de ejemplo del Módulo 2: una institución ficticia con niveles, grados, secciones, cursos, docentes
 * y algunas asignaciones. Idempotente y sin pisar lo que ya exista. Solo en local, staging y testing: en
 * producción lo rechaza. `DatabaseSeeder` lo llama solo en local; en staging se ejecuta a mano con
 * `db:seed --class=AcademicoDemoSeeder`.
 */
class AcademicoDemoSeeder extends Seeder
{
    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    /** nivel => [orden, nombres de grado] */
    private const NIVELES = [
        'Primaria' => [1, ['1°', '2°', '3°', '4°', '5°', '6°']],
        'Secundaria' => [2, ['1°', '2°', '3°', '4°', '5°']],
    ];

    private const CURSOS = ['Comunicación', 'Matemática', 'Personal Social', 'Ciencia y Tecnología', 'Arte y Cultura'];

    private const DOCENTES = ['docente.demo', 'docente.demo2', 'docente.demo3'];

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'AcademicoDemoSeeder solo puede ejecutarse en %s; el entorno actual es "%s".',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->call([RolesYPermisosSeeder::class, EscalasCalificacionSeeder::class]);

        DB::transaction(function () {
            $niveles = $this->niveles();
            $this->institucion($niveles);
            $anio = $this->anioVigente();
            $secciones = $this->gradosYSecciones($niveles, $anio);
            $this->cursos($niveles);
            $this->asignaciones($secciones);
        });
    }

    /** @return array<string, Nivel> */
    private function niveles(): array
    {
        $niveles = [];
        foreach (self::NIVELES as $nombre => [$orden]) {
            $niveles[$nombre] = Nivel::firstOrCreate(['nombre' => $nombre], ['orden' => $orden, 'activo' => true]);
        }

        return $niveles;
    }

    /** @param  array<string, Nivel>  $niveles */
    private function institucion(array $niveles): void
    {
        $ficha = Institucion::actual();

        if ($ficha->exists) {
            return;
        }

        $ficha->fill([
            'nombre' => 'Institución Educativa Demo',
            'codigo_modular' => '0000000',
            'ugel_dre' => 'UGEL Demo',
            'escala_calificacion_id' => EscalaCalificacion::where('codigo', 'literal')->value('id'),
        ])->save();
        $ficha->niveles()->sync(collect($niveles)->pluck('id')->all());
    }

    /** Usa el año vigente si hay uno; si no, crea «Demo» con 4 bimestres y lo marca vigente. */
    private function anioVigente(): AnioEscolar
    {
        $vigente = AnioEscolar::where('vigente', 1)->first();

        if ($vigente) {
            return $vigente;
        }

        $anio = app(AnioEscolarService::class)->guardar(null, [
            'nombre' => 'Demo '.now()->year,
            'fecha_inicio' => now()->year.'-03-01',
            'fecha_fin' => now()->year.'-12-20',
            'tipo_periodo' => TipoPeriodo::Bimestre,
        ], collect(TipoPeriodo::Bimestre->nombresSugeridos())->map(function (string $nombre, int $orden) {
            $inicio = Carbon::create(now()->year, 3, 1)->addMonthsNoOverflow(($orden - 1) * 2);

            return [
                'nombre' => $nombre,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $inicio->copy()->addMonthNoOverflow()->endOfMonth()->toDateString(),
            ];
        })->values()->all());

        app(AnioEscolarService::class)->marcarVigente($anio);

        return $anio->refresh();
    }

    /**
     * @param  array<string, Nivel>  $niveles
     * @return array<int, Seccion>
     */
    private function gradosYSecciones(array $niveles, AnioEscolar $anio): array
    {
        $secciones = [];

        foreach (self::NIVELES as $nombreNivel => [, $grados]) {
            foreach ($grados as $i => $nombreGrado) {
                $grado = Grado::firstOrCreate(
                    ['nivel_id' => $niveles[$nombreNivel]->id, 'nombre' => $nombreGrado],
                    ['orden' => $i + 1, 'activo' => true],
                );

                foreach (['A', 'B'] as $identificador) {
                    $secciones[] = Seccion::firstOrCreate(
                        ['grado_id' => $grado->id, 'anio_escolar_id' => $anio->id, 'identificador' => $identificador],
                        ['activo' => true],
                    );
                }
            }
        }

        return $secciones;
    }

    /** @param  array<string, Nivel>  $niveles */
    private function cursos(array $niveles): void
    {
        foreach ($niveles as $nivel) {
            $gradoIds = Grado::where('nivel_id', $nivel->id)->pluck('id')->all();

            foreach (self::CURSOS as $nombre) {
                $curso = Curso::firstOrCreate(['nivel_id' => $nivel->id, 'nombre' => $nombre], ['activo' => true]);
                $curso->grados()->syncWithoutDetaching($gradoIds);
            }
        }
    }

    /** @param  array<int, Seccion>  $secciones */
    private function asignaciones(array $secciones): void
    {
        $rolDocente = Rol::where('nombre', 'Docente')->firstOrFail();
        $docentes = [];

        foreach (self::DOCENTES as $i => $username) {
            $docentes[] = Usuario::firstOrCreate(['username' => $username], [
                'nombres' => 'Docente',
                'apellidos' => 'Demo '.($i + 1),
                'password' => 'password',
                'rol_id' => $rolDocente->id,
                'activo' => true,
            ]);
        }

        // Tres cursos de la primera sección y dos de la segunda, a docentes distintos.
        foreach (array_slice($secciones, 0, 2) as $n => $seccion) {
            $cursos = $seccion->grado->cursos()->orderBy('cursos.nombre')->limit($n === 0 ? 3 : 2)->get();

            foreach ($cursos as $j => $curso) {
                AsignacionDocente::firstOrCreate(
                    ['seccion_id' => $seccion->id, 'curso_id' => $curso->id],
                    ['usuario_id' => $docentes[($n + $j) % count($docentes)]->id],
                );
            }
        }
    }
}
