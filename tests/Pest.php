<?php

use App\Asistente\Proveedores\ProveedorIA;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\EscalaCalificacion;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Seccion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Factories\AsignacionDocenteFactory;
use Database\Factories\AsistenciaFactory;
use Database\Seeders\EscalasCalificacionSeeder;
use Database\Seeders\EstadosAsistenciaSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Las pruebas Unit pueden usar el contenedor y config(), pero nunca la base de datos.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Crea un usuario activo cuyo rol (de fábrica) tiene exactamente los permisos indicados
 * por identificador; los permisos que no existan se crean.
 *
 * @param  array<int, string>  $identificadores
 * @param  array<string, mixed>  $atributos
 */
function usuarioConPermisos(array $identificadores = [], array $atributos = []): Usuario
{
    $rol = Rol::factory()->create();

    $ids = collect($identificadores)->map(fn (string $identificador) => Permiso::firstOrCreate(
        ['identificador' => $identificador],
        ['nombre' => $identificador],
    )->id);

    $rol->permisos()->sync($ids->all());

    return Usuario::factory()->create(array_merge(['rol_id' => $rol->id, 'activo' => true], $atributos));
}

/**
 * Escenario de asignación: año vigente con una sección y cursos del grado, y un docente elegible.
 *
 * @return array{anio: AnioEscolar, seccion: Seccion, cursos: Collection, docente: Usuario}
 */
function escenarioAsignacion(int $cursos = 3): array
{
    $anio = AnioEscolar::factory()->vigente()->create();
    $grado = Grado::factory()->create();
    $seccion = Seccion::factory()->for($grado)->for($anio, 'anio')->create(['identificador' => 'A']);
    $listaCursos = Curso::factory()->count($cursos)->create(['nivel_id' => $grado->nivel_id]);
    $grado->cursos()->attach($listaCursos->pluck('id'));

    return [
        'anio' => $anio,
        'seccion' => $seccion,
        'cursos' => $listaCursos,
        'docente' => AsignacionDocenteFactory::docenteElegible(),
    ];
}

/** Usuario activo con el rol y los permisos que deja el seeder base. */
function usuarioConRolDeSeeder(string $nombreRol): Usuario
{
    test()->seed(RolesYPermisosSeeder::class);

    return Usuario::factory()->create(['rol_id' => Rol::where('nombre', $nombreRol)->firstOrFail()->id, 'activo' => true]);
}

/**
 * Escenario de matrícula: año vigente con una sección activa. Alumnos siempre ficticios (Ley 29733).
 *
 * @return array{anio: AnioEscolar, seccion: Seccion}
 */
function escenarioMatricula(): array
{
    $anio = AnioEscolar::factory()->vigente()->create();
    $seccion = Seccion::factory()->for(Grado::factory()->create())->for($anio, 'anio')->create(['identificador' => 'A']);

    return ['anio' => $anio, 'seccion' => $seccion];
}

/**
 * Escenario de asistencia: año vigente que cubre la fecha actual, sección, alumnos matriculados (ficticios),
 * un Docente asignado con permiso y un Administrador. Asegura el catálogo de estados.
 *
 * @return array{anio: AnioEscolar, seccion: Seccion, alumnos: Collection, docente: Usuario, admin: Usuario}
 */
function escenarioAsistencia(int $alumnos = 3): array
{
    (new EstadosAsistenciaSeeder)->run();

    $anio = AnioEscolar::factory()->vigente()->create([
        'fecha_inicio' => now()->subYear()->toDateString(),
        'fecha_fin' => now()->addYear()->toDateString(),
    ]);
    $seccion = Seccion::factory()->for(Grado::factory()->create())->for($anio, 'anio')->create(['identificador' => 'A']);
    $lista = Alumno::factory()->count($alumnos)->create();
    $lista->each(fn ($a) => Matricula::factory()->create([
        'alumno_id' => $a->id, 'seccion_id' => $seccion->id, 'fecha_matricula' => now()->subYear()->toDateString(),
    ]));
    $docente = usuarioConPermisos(['asistencia.registrar_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $docente->id]);

    return [
        'anio' => $anio, 'seccion' => $seccion, 'alumnos' => $lista, 'docente' => $docente,
        'admin' => usuarioConPermisos(['asistencia.gestionar', 'asistencia.ver_todas']),
    ];
}

/** Último día hábil (lunes a viernes) no posterior a hoy en la zona de la institución. */
function diaHabilReciente(int $atras = 0): CarbonImmutable
{
    $dia = CarbonImmutable::parse(now(config('asistencia.zona_horaria'))->toDateString());

    while ($dia->isWeekend()) {
        $dia = $dia->subDay();
    }

    for ($i = 0; $i < $atras; $i++) {
        $dia = $dia->subDay();
        while ($dia->isWeekend()) {
            $dia = $dia->subDay();
        }
    }

    return $dia;
}

/**
 * Escenario de evaluación: año vigente con un periodo que cubre hoy, sección con alumnos matriculados (ficticios),
 * un curso, un Docente asignado a (sección, curso) y un Administrador. Escala vigesimal en la institución.
 *
 * @return array{anio: AnioEscolar, periodo: PeriodoEvaluacion, seccion: Seccion, curso: Curso, alumnos: Collection, docente: Usuario, admin: Usuario}
 */
function escenarioEvaluacion(int $alumnos = 3, string $escala = 'vigesimal'): array
{
    (new EscalasCalificacionSeeder)->run();
    $institucion = Institucion::factory()->create(['escala_calificacion_id' => EscalaCalificacion::where('codigo', $escala)->value('id')]);

    $anio = AnioEscolar::factory()->vigente()->create([
        'fecha_inicio' => now()->subMonths(6)->toDateString(),
        'fecha_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $periodo = PeriodoEvaluacion::factory()->create([
        'anio_escolar_id' => $anio->id, 'orden' => 1, 'nombre' => 'Periodo 1',
        'fecha_inicio' => now()->subMonths(2)->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString(),
    ]);
    $grado = Grado::factory()->create();
    $seccion = Seccion::factory()->for($grado)->for($anio, 'anio')->create(['identificador' => 'A']);
    $curso = Curso::factory()->create(['nivel_id' => $grado->nivel_id]);
    $lista = Alumno::factory()->count($alumnos)->create();
    $lista->each(fn ($a) => Matricula::factory()->create([
        'alumno_id' => $a->id, 'seccion_id' => $seccion->id, 'fecha_matricula' => now()->subMonths(5)->toDateString(),
    ]));
    $docente = usuarioConPermisos(['notas.registrar_propia', 'docencia.dictar']);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'usuario_id' => $docente->id]);

    return [
        'institucion' => $institucion, 'anio' => $anio, 'periodo' => $periodo, 'seccion' => $seccion, 'curso' => $curso,
        'alumnos' => $lista, 'docente' => $docente,
        'admin' => usuarioConPermisos(['notas.gestionar', 'notas.ver_todas', 'notas.cerrar_periodo', 'boletas.ver_todas']),
    ];
}

/**
 * Guarda un esquema de criterios (por defecto 60 % y 40 %) con el Administrador del escenario.
 *
 * @param  array<string, mixed>  $e  resultado de `escenarioEvaluacion()`
 * @param  list<array{nombre: string, peso: int|float|string}>|null  $criterios
 * @return Collection<int, CriterioEvaluacion>
 */
function esquemaEvaluacion(array $e, ?array $criterios = null): Collection
{
    return app(EvaluacionService::class)->guardarEsquema(
        $e['seccion'], $e['curso'], $e['periodo'],
        $criterios ?? [['nombre' => 'Práctica', 'peso' => 60], ['nombre' => 'Examen', 'peso' => 40]],
        $e['admin'],
    );
}

/** Escenario con un alumno con notas 15/10 en el periodo y, opcionalmente, el periodo cerrado. */
function escenarioBoleta(bool $cerrar = true, string $escala = 'vigesimal'): array
{
    $e = escenarioEvaluacion(2, $escala);
    $criterios = esquemaEvaluacion($e);
    [$ana, $beto] = $e['alumnos']->all();
    $ana->update(['apellidos' => 'Alfa', 'nombres' => 'Ana']);
    $valores = $escala === 'literal' ? ['AD', 'C'] : ['15', '10'];
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [
        $ana->id => [$criterios[0]->id => $valores[0], $criterios[1]->id => $valores[1]],
    ], $e['admin']);
    $cerrar && CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    return $e + ['ana' => $ana, 'beto' => $beto, 'criterios' => $criterios];
}

/**
 * Escenario del asistente: boleta con notas 15/10 de Ana (promedio 13) y periodo publicado (o no), asistencia de Ana
 * (1 presente y 1 falta), un Padre de Familia con dos hijos (Ana y Beto) y un Alumno con cuenta propia (Ana).
 * Los datos personales de Ana tienen valores reconocibles para buscarlos en lo enviado al proveedor.
 *
 * @return array<string, mixed>
 */
function escenarioAsistente(bool $cerrar = true): array
{
    $e = escenarioBoleta($cerrar);
    (new EstadosAsistenciaSeeder)->run();

    $e['ana']->update([
        'numero_documento' => '47851236', 'fecha_nacimiento' => '2011-03-17', 'nombres' => 'Ana', 'apellidos' => 'Alfa',
    ]);
    $e['beto']->update(['nombres' => 'Beto', 'apellidos' => 'Beta', 'numero_documento' => '39284756']);

    $permisos = ['asistente.usar', 'notas.ver_propia', 'asistencia.ver_propia', 'matricula.ver_propia'];
    $padre = usuarioConPermisos($permisos, ['email' => 'padre.reconocible@ejemplo.test']);
    $e['ana']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);
    $e['beto']->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    $alumno = usuarioConPermisos($permisos, ['email' => 'ana.reconocible@ejemplo.test']);
    $e['ana']->update(['usuario_id' => $alumno->id]);

    foreach (['presente', 'falta'] as $i => $codigo) {
        AsistenciaFactory::new()->create([
            'seccion_id' => $e['seccion']->id, 'alumno_id' => $e['ana']->id, 'fecha' => now()->startOfMonth()->addDays($i)->toDateString(),
            'estado_id' => AsistenciaFactory::new()->catalogo($codigo)->id,
        ]);
    }

    return $e + ['padre' => $padre, 'alumno_usuario' => $alumno, 'permisos' => $permisos, 'falso' => app(ProveedorIA::class)];
}
