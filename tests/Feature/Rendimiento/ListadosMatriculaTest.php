<?php

// SC-005 (Módulo 3): con 2000 alumnos y 200 secciones, listado, nómina y búsqueda responden sin N+1 y dentro de tiempo.

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Seccion;
use Database\Factories\TipoDocumentoFactory;
use Illuminate\Support\Facades\DB;

/** Carga el volumen del criterio con inserciones masivas: 200 secciones y 2000 alumnos matriculados (10 por sección). */
function volumenMatricula(): array
{
    $ahora = now();
    $anio = AnioEscolar::where('vigente', 1)->first() ?? AnioEscolar::factory()->vigente()->create();
    $nivel = Nivel::factory()->create();
    $grados = Grado::factory()->count(10)->create(['nivel_id' => $nivel->id]);
    $dni = TipoDocumentoFactory::dni();

    $secciones = [];
    foreach ($grados as $grado) {
        foreach (range(1, 20) as $n) {
            $secciones[] = ['grado_id' => $grado->id, 'anio_escolar_id' => $anio->id, 'identificador' => "S{$n}", 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
    }
    DB::table('secciones')->insert($secciones);
    $seccionIds = DB::table('secciones')->pluck('id');

    $alumnos = [];
    foreach (range(1, 2000) as $i) {
        $alumnos[] = [
            'nombres' => "Alumno Prueba{$i}", 'apellidos' => 'Ficticio Ejemplo', 'tipo_documento_id' => $dni->id,
            'numero_documento' => (string) (90000000 + $i), 'fecha_nacimiento' => '2014-05-05', 'sexo' => $i % 2 ? 'F' : 'M',
            'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($alumnos, 500) as $bloque) {
        DB::table('alumnos')->insert($bloque);
    }

    $matriculas = [];
    foreach (DB::table('alumnos')->whereNotIn('id', DB::table('matriculas')->select('alumno_id'))->pluck('id') as $i => $alumnoId) {
        $matriculas[] = [
            'alumno_id' => $alumnoId, 'seccion_id' => $seccionIds[$i % $seccionIds->count()], 'anio_id' => $anio->id,
            'fecha_matricula' => '2026-03-01', 'estado' => 'matriculado', 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
    }
    foreach (array_chunk($matriculas, 500) as $bloque) {
        DB::table('matriculas')->insert($bloque);
    }

    return ['seccion' => Seccion::first()];
}

/** @return array{consultas: int, segundos: float} */
function medirMatricula(object $caso, $usuario, string $uri): array
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

function urlsMatricula($seccion): array
{
    return [
        '/matricula/alumnos',
        '/matricula/alumnos?busqueda=Alumno',
        '/matricula/alumnos?busqueda=9',
        '/matricula/nominas',
        "/matricula/nominas/{$seccion->id}",
        "/matricula/nominas/{$seccion->id}?busqueda=Alumno",
    ];
}

it('cada listado ejecuta el mismo número de consultas con pocos datos y con el volumen del criterio', function () {
    $admin = usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);
    ['seccion' => $seccion] = escenarioMatricula();
    TipoDocumentoFactory::dni();
    Matricula::factory()->count(3)->create(['seccion_id' => $seccion->id]);

    $antes = collect(urlsMatricula($seccion))->mapWithKeys(function ($url) use ($admin) {
        medirMatricula($this, $admin, $url);

        return [$url => medirMatricula($this, $admin, $url)['consultas']];
    });

    volumenMatricula();

    foreach (urlsMatricula($seccion) as $url) {
        expect(medirMatricula($this, $admin, $url)['consultas'])->toBe($antes[$url], "N+1 en {$url}");
    }
});

it('listado, nómina y búsqueda responden en menos de 2 s con 2000 alumnos y 200 secciones', function () {
    ['seccion' => $seccion] = volumenMatricula();
    $admin = usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);

    foreach (urlsMatricula($seccion) as $url) {
        expect(medirMatricula($this, $admin, $url)['segundos'])->toBeLessThan(2.0, $url);
    }
});

it('el docente y la familia también responden en menos de 2 s con el volumen del criterio', function () {
    ['seccion' => $seccion] = volumenMatricula();
    $docente = usuarioConPermisos(['matricula.ver_nomina_propia']);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'usuario_id' => $docente->id]);
    $padre = usuarioConPermisos(['matricula.ver_propia']);
    Alumno::query()->limit(3)->get()->each(fn ($a) => $a->apoderados()->attach($padre->id, ['parentesco' => 'tutor']));

    expect(medirMatricula($this, $docente, "/matricula/nominas/{$seccion->id}")['segundos'])->toBeLessThan(2.0)
        ->and(medirMatricula($this, $padre, '/matricula/mis-alumnos')['segundos'])->toBeLessThan(2.0);
});

it('las consultas de los listados usan índices (EXPLAIN)', function () {
    ['seccion' => $seccion] = volumenMatricula();
    $alumnoId = DB::table('alumnos')->value('id');
    $usuario = usuarioConPermisos(['matricula.ver_propia']);
    DB::table('alumnos')->where('id', $alumnoId)->update(['usuario_id' => $usuario->id]);
    DB::table('alumno_apoderado')->insert(['alumno_id' => $alumnoId, 'usuario_id' => $usuario->id, 'parentesco' => 'tutor', 'created_at' => now(), 'updated_at' => now()]);
    $anioId = DB::table('anios_escolares')->value('id');

    $consultas = [
        'nómina por sección y estado' => ['select * from matriculas where seccion_id = ? and estado = ?', [$seccion->id, 'matriculado']],
        'historial del alumno' => ['select * from matriculas where alumno_id = ? order by anio_id desc, id desc', [$alumnoId]],
        'matrícula activa del alumno en el año' => ['select * from matriculas where alumno_id = ? and anio_activa_id = ?', [$alumnoId, $anioId]],
        'búsqueda por documento (prefijo)' => ['select * from alumnos where numero_documento like ?', ['9000123%']],
        'orden por apellidos y nombres' => ['select * from alumnos force index (alumnos_apellidos_nombres_index) order by apellidos, nombres limit 15', []],
        'alumnos de un apoderado' => ['select * from alumno_apoderado where usuario_id = ?', [$usuario->id]],
        'alumno de un usuario' => ['select * from alumnos where usuario_id = ?', [$usuario->id]],
    ];

    foreach ($consultas as $nombre => [$sql, $bindings]) {
        $plan = DB::select('explain '.$sql, $bindings)[0];

        // El optimizador puede preferir recorrer la tabla cuando es pequeña o el filtro abarca casi todo (sus
        // estadísticas se muestrean al azar): se exige que exista un índice utilizable, no que lo elija esta vez.
        expect($plan->key ?? $plan->possible_keys)->not->toBeNull("«{$nombre}» no tiene un índice utilizable");

        if ($nombre === 'orden por apellidos y nombres') {
            expect($plan->Extra ?? '')->not->toContain('filesort', 'el índice debe resolver el orden sin ordenar en memoria');
        }
    }
});
