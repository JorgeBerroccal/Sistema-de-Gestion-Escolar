<?php

// SC-005 (Módulo 4): el reporte de una sección con un año de registros responde sin N+1 y dentro de tiempo.

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Matricula;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** 30 alumnos matriculados con ~200 días hábiles de asistencia cada uno, por inserción masiva. */
function volumenAsistencia(int $alumnos = 30, int $dias = 200, ?array $e = null): array
{
    $e ??= escenarioAsistencia(0);
    $estados = EstadoAsistencia::pluck('id', 'codigo')->values()->all();
    $lista = Alumno::factory()->count($alumnos)->create();
    $lista->each(fn ($a) => Matricula::factory()->create(['alumno_id' => $a->id, 'seccion_id' => $e['seccion']->id, 'fecha_matricula' => now()->subYear()->toDateString()]));

    $ahora = now();
    $fechas = [];
    for ($d = CarbonImmutable::parse('2026-03-02'); count($fechas) < $dias; $d = $d->addDay()) {
        if (! $d->isWeekend()) {
            $fechas[] = $d->toDateString();
        }
    }

    $filas = [];
    foreach ($lista as $n => $alumno) {
        foreach ($fechas as $i => $fecha) {
            $filas[] = ['seccion_id' => $e['seccion']->id, 'alumno_id' => $alumno->id, 'fecha' => $fecha, 'estado_id' => $estados[($n + $i) % 4],
                'registrado_por' => $e['admin']->id, 'created_at' => $ahora, 'updated_at' => $ahora];
        }
    }
    foreach (array_chunk($filas, 1000) as $bloque) {
        DB::table('asistencias')->insert($bloque);
    }

    return $e + ['desde' => $fechas[0], 'hasta' => end($fechas)];
}

function medirAsistencia(object $caso, $usuario, string $uri): array
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

it('el reporte y el detalle de una sección con un año de registros responden en menos de 3 s', function () {
    $v = volumenAsistencia();
    $alumno = Alumno::first();
    $urls = [
        "/asistencia/reportes?seccion={$v['seccion']->id}&desde={$v['desde']}&hasta={$v['hasta']}",
        "/asistencia/reportes/{$v['seccion']->id}/alumnos/{$alumno->id}?desde={$v['desde']}&hasta={$v['hasta']}",
        "/asistencia/tomar/{$v['seccion']->id}",
    ];

    foreach ($urls as $url) {
        expect(medirAsistencia($this, $v['admin'], $url)['segundos'])->toBeLessThan(3.0, $url);
    }
});

it('cada pantalla ejecuta el mismo número de consultas con pocos y con muchos datos (sin N+1)', function () {
    $admin = usuarioConPermisos(['asistencia.ver_todas', 'asistencia.gestionar']);
    $chico = escenarioAsistencia(2);
    $url = fn ($s) => "/asistencia/reportes?seccion={$s->id}";
    // Con al menos un registro, para comparar el mismo camino (un ranking vacío ahorra una consulta de paginación).
    Asistencia::factory()->create(['seccion_id' => $chico['seccion']->id, 'alumno_id' => $chico['alumnos'][0]->id, 'fecha' => diaHabilReciente()->toDateString(), 'registrado_por' => $admin->id]);
    medirAsistencia($this, $admin, $url($chico['seccion'])); // calienta cachés de permisos y sesión
    $antes = medirAsistencia($this, $admin, $url($chico['seccion']))['consultas'];

    $v = volumenAsistencia(e: $chico);

    medirAsistencia($this, $admin, $url($v['seccion']));
    expect(medirAsistencia($this, $admin, $url($v['seccion']))['consultas'])->toBe($antes);
});

it('las consultas del reporte tienen un índice utilizable (EXPLAIN)', function () {
    $v = volumenAsistencia(5, 20);
    $alumnoId = DB::table('alumnos')->value('id');
    DB::statement('analyze table asistencias');

    $consultas = [
        'reporte por sección y fecha' => ['select * from asistencias where seccion_id = ? and fecha between ? and ?', [$v['seccion']->id, '2026-03-02', '2026-04-30']],
        'asistencia de un alumno' => ['select * from asistencias where alumno_id = ? and fecha between ? and ?', [$alumnoId, '2026-03-02', '2026-04-30']],
        'registro único' => ['select * from asistencias where seccion_id = ? and alumno_id = ? and fecha = ?', [$v['seccion']->id, $alumnoId, '2026-03-02']],
    ];

    foreach ($consultas as $nombre => [$sql, $bindings]) {
        $plan = DB::select('explain '.$sql, $bindings)[0];

        expect($plan->key ?? $plan->possible_keys)->not->toBeNull("«{$nombre}» no tiene un índice utilizable");
    }
});
