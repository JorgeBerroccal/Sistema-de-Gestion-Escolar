<?php

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\Curso;
use App\Models\Matricula;
use Illuminate\Support\Facades\DB;

/** Sección con 3 alumnos: Ana 15/10 → 13; Beto y Carla sin notas; luego se ajusta según la prueba. */
function reporteConNotas(array $notasPorAlumno, string $escala = 'vigesimal'): array
{
    $e = escenarioEvaluacion(count($notasPorAlumno), $escala);
    $criterios = esquemaEvaluacion($e);
    $datos = [];

    foreach ($e['alumnos']->values() as $i => $alumno) {
        $alumno->update(['apellidos' => 'Apellido'.$i, 'nombres' => 'Nombre'.$i]);

        if ($notasPorAlumno[$i] !== null) {
            $datos[$alumno->id] = [$criterios[0]->id => $notasPorAlumno[$i][0], $criterios[1]->id => $notasPorAlumno[$i][1]];
        }
    }

    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $datos, $e['admin']);

    return $e + ['criterios' => $criterios];
}

it('calcula promedio por curso, promedio general, orden de mérito y desaprobados', function () {
    $e = reporteConNotas([['15', '10'], ['18', '18'], ['08', '10'], null]);

    $r = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);

    expect($r['sin_datos'])->toBeFalse()
        ->and($r['alumnos']->total())->toBe(4)
        ->and(collect($r['alumnos']->items())->pluck('puesto')->all())->toBe([1, 2, 3, null])
        ->and($r['alumnos']->items()[0]['promedio'])->toBe(18.0)
        ->and($r['alumnos']->items()[1]['promedio'])->toBe(13.0)
        ->and($r['cursos'])->toHaveCount(1)
        ->and($r['cursos'][0]['desaprobados'])->toBe(1)
        ->and($r['cursos'][0]['promedio'])->toBe(round((18 + 13 + 8.8) / 3, 2));
});

it('los empates se desempatan por apellidos, nombres e id y los sin notas quedan fuera del orden', function () {
    $e = reporteConNotas([['15', '15'], ['15', '15'], null]);
    $e['alumnos']->values()[0]->update(['apellidos' => 'Zeta']);
    $e['alumnos']->values()[1]->update(['apellidos' => 'Alfa']);

    $r = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);
    $filas = collect($r['alumnos']->items());

    expect($filas->pluck('estudiante.apellidos')->take(2)->all())->toBe(['Alfa', 'Zeta'])
        ->and($filas->pluck('puesto')->all())->toBe([1, 2, null]);
});

it('sección sin notas responde «sin datos»', function () {
    $e = escenarioEvaluacion(2);
    esquemaEvaluacion($e);

    $r = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);

    expect($r['sin_datos'])->toBeTrue()->and($r['alumnos']->total())->toBe(2);
});

it('con escala literal muestra los calificativos', function () {
    $e = reporteConNotas([['AD', 'AD'], ['C', 'C']], 'literal');

    $r = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);

    expect($r['alumnos']->items()[0]['etiqueta'])->toBe('AD')->and($r['alumnos']->items()[1]['etiqueta'])->toBe('C')
        ->and($r['cursos'][0]['desaprobados'])->toBe(1);
});

it('el Docente ve solo los cursos que dicta y su promedio se calcula sobre ellos', function () {
    $e = reporteConNotas([['20', '20'], ['10', '10']]);
    $otro = Curso::factory()->create(['nivel_id' => $e['curso']->nivel_id]);
    $criteriosOtro = app(EvaluacionService::class)->guardarEsquema($e['seccion'], $otro, $e['periodo'], [['nombre' => 'Único', 'peso' => 100]], $e['admin']);
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $otro, $e['periodo'], [
        $e['alumnos'][0]->id => [$criteriosOtro[0]->id => '10'],
    ], $e['admin']);

    $admin = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);
    $docente = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['docente']);

    expect($admin['cursos'])->toHaveCount(2)
        ->and($docente['cursos'])->toHaveCount(1)->and($docente['cursos'][0]['curso']->id)->toBe($e['curso']->id)
        ->and($docente['alumnos']->items()[0]['promedio'])->toBe(20.0)
        ->and($admin['alumnos']->items()[0]['promedio'])->toBe(15.0);
});

it('pagina el listado sin N+1', function () {
    $e = escenarioEvaluacion(1);
    esquemaEvaluacion($e);
    Alumno::factory()->count(20)->create()->each(fn ($a) => Matricula::factory()->create([
        'alumno_id' => $a->id, 'seccion_id' => $e['seccion']->id, 'fecha_matricula' => now()->subMonth()->toDateString(),
    ]));

    DB::enableQueryLog();
    $r = app(ConsultaNotas::class)->reporteSeccion($e['seccion'], $e['periodo'], $e['admin']);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($r['alumnos']->total())->toBe(21)->and($r['alumnos']->count())->toBe(15)->and($consultas)->toBeLessThan(12);
});
