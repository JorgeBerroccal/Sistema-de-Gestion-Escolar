<?php

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Evaluacion\EvaluacionService;
use App\Livewire\Asistencia\Propia\Index as AsistenciaPropia;
use App\Livewire\Evaluacion\Propia\Index as NotasPropias;
use App\Models\Alumno;
use App\Models\Institucion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/** Optimizaciones de la rampa 1 (sesión k6 del 13/11): menos consultas por solicitud, mismo resultado. */
function consultasQueMencionan(string $tabla, callable $accion): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $accion();
    $total = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], "`{$tabla}`") || str_contains($q['query'], "\"{$tabla}\""))->count();
    DB::disableQueryLog();

    return $total;
}

it('alumnosPropios devuelve el alumno vinculado más los hijos, sin repetidos ni ajenos', function () {
    $usuario = usuarioConPermisos(['notas.ver_propia']);
    $propio = Alumno::factory()->create(['usuario_id' => $usuario->id]);
    $hijo = Alumno::factory()->create();
    $hijo->apoderados()->attach($usuario->id, ['parentesco' => 'padre']);
    Alumno::factory()->create(); // ajeno

    expect(MatriculaRegla::alumnosPropios($usuario))->toEqualCanonicalizing([$propio->id, $hijo->id]);
});

it('alumnosPropios sin vínculos es una lista vacía y no deja ver alumnos (fail-closed)', function () {
    $usuario = usuarioConPermisos(['notas.ver_propia']);
    Alumno::factory()->count(2)->create();

    expect(MatriculaRegla::alumnosPropios($usuario))->toBe([])
        ->and(Alumno::query()->whereIn('alumnos.id', MatriculaRegla::alumnosPropios($usuario))->count())->toBe(0);
});

it('alumnosPropios no usa UNION y se apoya en índices de usuario_id', function () {
    $usuario = usuarioConPermisos(['notas.ver_propia']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    MatriculaRegla::alumnosPropios($usuario);
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
    DB::disableQueryLog();

    expect(strtolower($sql))->not->toContain('union');
    foreach (['alumnos', 'alumno_apoderado'] as $tabla) {
        expect(collect(Schema::getIndexes($tabla))->pluck('columns')->flatten()->contains('usuario_id'))->toBeTrue("falta índice en {$tabla}.usuario_id");
    }
});

it('EvaluacionService lee la institución y la escala una sola vez por solicitud', function () {
    escenarioEvaluacion(1, 'vigesimal');
    $servicio = app(EvaluacionService::class);

    $consultas = consultasQueMencionan('instituciones', function () use ($servicio) {
        $servicio->escala();
        $servicio->escala();
        $servicio->calculo();
        $servicio->institucion();
    });

    expect($consultas)->toBe(1)
        ->and(app(EvaluacionService::class))->toBe($servicio)
        ->and($servicio->institucion())->toBeInstanceOf(Institucion::class);
});

it('las notas propias consultan los alumnos propios una sola vez por solicitud', function () {
    $padre = usuarioConPermisos(['notas.ver_propia']);
    Alumno::factory()->create()->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    $consultas = consultasQueMencionan('alumno_apoderado', fn () => Livewire::actingAs($padre)->test(NotasPropias::class)->assertOk());

    expect($consultas)->toBe(1);
});

it('la asistencia propia consulta los alumnos propios una sola vez por solicitud', function () {
    $padre = usuarioConPermisos(['asistencia.ver_propia']);
    Alumno::factory()->create()->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    $consultas = consultasQueMencionan('alumno_apoderado', fn () => Livewire::actingAs($padre)->test(AsistenciaPropia::class)->assertOk());

    expect($consultas)->toBe(1);
});
