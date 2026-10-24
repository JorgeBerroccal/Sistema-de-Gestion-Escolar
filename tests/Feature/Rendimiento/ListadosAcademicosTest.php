<?php

// SC-005: con 200 secciones y 2000 asignaciones, los listados responden sin N+1 y dentro de tiempo.

use App\Models\AnioEscolar;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Permiso;
use Database\Factories\AsignacionDocenteFactory;
use Illuminate\Support\Facades\DB;

const RUTAS_LISTADO = ['niveles', 'anios', 'grados', 'secciones', 'cursos', 'asignaciones'];

/** Carga el volumen del criterio con inserciones masivas: 200 secciones × 10 cursos = 2000 asignaciones. */
function volumenAcademico(): array
{
    $ahora = now();
    $anio = AnioEscolar::factory()->vigente()->create();
    $nivel = Nivel::factory()->create();
    $grados = Grado::factory()->count(10)->create(['nivel_id' => $nivel->id]);
    $cursos = Curso::factory()->count(10)->create(['nivel_id' => $nivel->id]);
    $docentes = collect(range(1, 20))->map(fn () => AsignacionDocenteFactory::docenteElegible());

    $filas = [];
    foreach ($grados as $grado) {
        foreach (range(1, 20) as $n) {
            $filas[] = ['grado_id' => $grado->id, 'anio_escolar_id' => $anio->id, 'identificador' => "S{$n}", 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
    }
    DB::table('secciones')->insert($filas);

    $grado_curso = [];
    foreach ($grados as $grado) {
        foreach ($cursos as $curso) {
            $grado_curso[] = ['curso_id' => $curso->id, 'grado_id' => $grado->id];
        }
    }
    DB::table('curso_grado')->insert($grado_curso);

    $asignaciones = [];
    foreach (DB::table('secciones')->pluck('id') as $i => $seccionId) {
        foreach ($cursos as $j => $curso) {
            $asignaciones[] = ['seccion_id' => $seccionId, 'curso_id' => $curso->id, 'usuario_id' => $docentes[($i + $j) % 20]->id, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
    }
    foreach (array_chunk($asignaciones, 500) as $bloque) {
        DB::table('asignaciones_docentes')->insert($bloque);
    }

    return ['docente' => $docentes[0]];
}

/** @return array{consultas: int, segundos: float} */
function medirGet(object $caso, $usuario, string $uri): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $inicio = microtime(true);
    $caso->actingAs($usuario)->get($uri)->assertOk();
    $segundos = microtime(true) - $inicio;
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['consultas' => $consultas, 'segundos' => $segundos];
}

it('cada listado ejecuta el mismo número de consultas con pocos datos y con el volumen del criterio', function () {
    $admin = usuarioConPermisos(['academico.ver', 'asignaciones.ver_todas']);
    // La línea base ya incluye algunas filas para que los listados no estén vacíos.
    AsignacionDocenteFactory::new()->count(2)->create();

    // Se calienta la instancia del usuario (memoiza sus permisos) para medir solo las consultas del listado.
    $antes = collect(RUTAS_LISTADO)->mapWithKeys(function ($r) use ($admin) {
        medirGet($this, $admin, "/academico/{$r}");

        return [$r => medirGet($this, $admin, "/academico/{$r}")['consultas']];
    });

    volumenAcademico();

    foreach (RUTAS_LISTADO as $ruta) {
        expect(medirGet($this, $admin, "/academico/{$ruta}")['consultas'])->toBe($antes[$ruta], "N+1 en /academico/{$ruta}");
    }
});

it('los listados responden en menos de 2 s y «Mis asignaciones» en menos de 1,5 s con 200 secciones y 2000 asignaciones', function () {
    ['docente' => $docente] = volumenAcademico();
    $admin = usuarioConPermisos(['academico.ver', 'asignaciones.ver_todas']);

    foreach (RUTAS_LISTADO as $ruta) {
        expect(medirGet($this, $admin, "/academico/{$ruta}")['segundos'])->toBeLessThan(2.0, "/academico/{$ruta}");
    }

    $docente->rol->permisos()->attach(Permiso::firstOrCreate(['identificador' => 'asignaciones.ver_propias'], ['nombre' => 'propias'])->id);

    expect(medirGet($this, $docente->fresh(), '/academico/mis-asignaciones')['segundos'])->toBeLessThan(1.5);
});

it('las consultas de los listados usan índices (EXPLAIN)', function () {
    ['docente' => $docente] = volumenAcademico();
    $anioId = AnioEscolar::where('vigente', 1)->value('id');

    $consultas = [
        'mis asignaciones' => ['select * from asignaciones_docentes where usuario_id = ? order by id desc limit 15', [$docente->id]],
        'secciones por año' => ['select * from secciones where anio_escolar_id = ? order by grado_id, identificador limit 15', [$anioId]],
        'asignaciones por sección' => ['select * from asignaciones_docentes where seccion_id = ?', [DB::table('secciones')->value('id')]],
        'asignaciones por curso' => ['select * from asignaciones_docentes where curso_id = ?', [DB::table('cursos')->value('id')]],
        'grados por nivel' => ['select * from grados where nivel_id = ?', [DB::table('niveles')->value('id')]],
        'cursos por nivel' => ['select * from cursos where nivel_id = ?', [DB::table('niveles')->value('id')]],
    ];

    foreach ($consultas as $nombre => [$sql, $bindings]) {
        $plan = DB::select('explain '.$sql, $bindings)[0];

        expect($plan->key)->not->toBeNull("«{$nombre}» no usa índice")
            ->and($plan->type)->not->toBe('ALL', "«{$nombre}» recorre la tabla completa");
    }
});
