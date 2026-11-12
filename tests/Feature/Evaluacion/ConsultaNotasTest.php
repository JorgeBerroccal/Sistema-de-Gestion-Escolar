<?php

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function notasPublicadas(array $e, array $celdasPorAlumno, bool $cerrar = true): Collection
{
    $criterios = esquemaEvaluacion($e);
    $celdas = [];
    foreach ($celdasPorAlumno as $alumnoId => [$practica, $examen]) {
        $celdas[$alumnoId] = [$criterios[0]->id => $practica, $criterios[1]->id => $examen];
    }
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $e['admin']);
    $cerrar && CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    return $criterios;
}

it('devuelve por curso el detalle por criterio y el promedio del periodo', function () {
    $e = escenarioEvaluacion(1);
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['15', '10']]);

    $datos = app(ConsultaNotas::class)->delAlumno($ana, $e['periodo'], usuarioConPermisos(['notas.ver_todas']));
    $fila = collect($datos['cursos'])->firstWhere(fn ($f) => $f['curso']->id === $e['curso']->id);

    expect($fila['criterios'])->toHaveCount(2)
        ->and($fila['criterios'][0]['nota']->valor)->toBe('15')
        ->and($fila['promedio'])->toBe(13.0)
        ->and($fila['etiqueta'])->toBe('13,00')
        ->and($fila['aprobado'])->toBeTrue()
        ->and($datos['promedio'])->toBe(13.0);
});

it('un curso sin notas en el periodo muestra «sin notas» (null), no cero', function () {
    $e = escenarioEvaluacion(2);
    [$ana, $beto] = $e['alumnos']->all();
    notasPublicadas($e, [$ana->id => ['15', '10']]);

    $fila = collect(app(ConsultaNotas::class)->delAlumno($beto, $e['periodo'], usuarioConPermisos(['notas.ver_todas']))['cursos'])->first();

    expect($fila['promedio'])->toBeNull()->and($fila['etiqueta'])->toBeNull()->and($fila['aprobado'])->toBeNull();
});

it('el curso del plan de estudios sin criterios aparece sin notas', function () {
    $e = escenarioEvaluacion(1);
    $e['seccion']->grado->cursos()->attach($e['curso']->id);
    $ana = $e['alumnos']->first();
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    $datos = app(ConsultaNotas::class)->delAlumno($ana, $e['periodo'], usuarioConPermisos(['notas.ver_todas']));

    expect($datos['cursos'])->toHaveCount(1)
        ->and($datos['cursos'][0]['criterios'])->toBeEmpty()
        ->and($datos['cursos'][0]['promedio'])->toBeNull();
});

it('el periodo sin cierre es invisible para la familia pero la boleta (sin usuario) lo lee', function () {
    $e = escenarioEvaluacion(1);
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['15', '10']], cerrar: false);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $ana->apoderados()->attach($padre->id, ['parentesco' => 'padre']);
    $consulta = app(ConsultaNotas::class);

    expect($consulta->periodosPublicados($ana))->toBeEmpty()
        ->and($consulta->delAlumno($ana, $e['periodo'], $padre))->toBeNull()
        ->and($consulta->delAlumno($ana, $e['periodo'], null, $e['seccion']))->not->toBeNull();

    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    expect($consulta->periodosPublicados($ana)->pluck('id')->all())->toBe([$e['periodo']->id])
        ->and($consulta->delAlumno($ana, $e['periodo'], $padre)['cursos'])->not->toBeEmpty();
});

it('el promedio anual usa solo los periodos que tienen notas y no cuenta los demás como cero', function () {
    $e = escenarioEvaluacion(1);
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['10', '10']]);   // periodo 1 → 10
    $periodo2 = PeriodoEvaluacion::factory()->create(['anio_escolar_id' => $e['anio']->id, 'orden' => 2, 'nombre' => 'Periodo 2']);
    $periodo3 = PeriodoEvaluacion::factory()->create(['anio_escolar_id' => $e['anio']->id, 'orden' => 3, 'nombre' => 'Periodo 3']);
    $e2 = array_merge($e, ['periodo' => $periodo2]);
    notasPublicadas($e2, [$ana->id => ['20', '20']]);  // periodo 2 → 20
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $periodo3->id]); // sin notas

    $consulta = app(ConsultaNotas::class);
    $anual = $consulta->anualDelAlumno($ana, $consulta->periodosDelAnioAnual($ana), usuarioConPermisos(['notas.ver_todas']));

    expect($anual['periodos'])->toBe([$e['periodo']->id, $periodo2->id, $periodo3->id])
        ->and($anual['cursos'][0]['promedio'])->toBe(15.0)
        ->and($anual['promedio'])->toBe(15.0);
});

it('con escala literal el promedio se muestra como letra', function () {
    $e = escenarioEvaluacion(1, 'literal');
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['AD', 'C']]); // 4×0,6 + 1×0,4 = 2,8 → A

    $fila = app(ConsultaNotas::class)->delAlumno($ana, $e['periodo'], usuarioConPermisos(['notas.ver_todas']))['cursos'][0];

    expect($fila['promedio'])->toBe(2.8)->and($fila['etiqueta'])->toBe('A')->and($fila['criterios'][0]['etiqueta'])->toBe('AD');
});

it('un alumno trasladado conserva las notas de la sección original y no pierde el periodo', function () {
    $e = escenarioEvaluacion(1);
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['16', '16']]);
    Matricula::where('alumno_id', $ana->id)->update(['estado' => 'trasladado', 'fecha_estado' => now()->toDateString()]);
    $destino = Seccion::factory()->for($e['seccion']->grado)->for($e['anio'], 'anio')->create(['identificador' => 'B']);
    Matricula::factory()->create(['alumno_id' => $ana->id, 'seccion_id' => $destino->id]);
    CierrePeriodo::factory()->create(['seccion_id' => $destino->id, 'periodo_id' => $e['periodo']->id]);

    $consulta = app(ConsultaNotas::class);

    expect($consulta->seccionDelPeriodo($ana, $e['periodo'])->id)->toBe($e['seccion']->id)
        ->and($consulta->delAlumno($ana, $e['periodo'], usuarioConPermisos(['notas.ver_todas']))['cursos'][0]['promedio'])->toBe(16.0);
});

it('las consultas no crecen con el número de cursos (sin N+1)', function () {
    $e = escenarioEvaluacion(1);
    $ana = $e['alumnos']->first();
    notasPublicadas($e, [$ana->id => ['10', '10']]);
    $admin = usuarioConPermisos(['notas.ver_todas']);
    $consulta = app(ConsultaNotas::class);
    $contar = function () use ($consulta, $ana, $e, $admin) {
        $n = 0;
        $consulta->delAlumno($ana, $e['periodo'], $admin); // caliente: rol y permisos del usuario ya cargados
        DB::flushQueryLog();
        DB::enableQueryLog();
        $consulta->delAlumno($ana, $e['periodo'], $admin);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    $antes = $contar();
    foreach (range(1, 6) as $i) {
        $curso = Curso::factory()->create(['nivel_id' => $e['curso']->nivel_id]);
        $e['seccion']->grado->cursos()->attach($curso->id);
        CriterioEvaluacion::factory()->create(['seccion_id' => $e['seccion']->id, 'curso_id' => $curso->id, 'periodo_id' => $e['periodo']->id]);
    }

    expect($contar())->toBe($antes);
});

it('sin periodos ni matrícula en el año no hay año anual', function () {
    $ana = Alumno::factory()->create();

    expect(app(ConsultaNotas::class)->periodosDelAnioAnual($ana))->toBeEmpty()
        ->and(app(ConsultaNotas::class)->periodosDelAnioAnual($ana, soloPublicados: false))->toBeEmpty();
});
