<?php

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\CriterioEvaluacion;
use App\Models\Nota;

it('guarda un esquema que suma 100 % y lo devuelve en orden', function () {
    $e = escenarioEvaluacion(1);

    $criterios = esquemaEvaluacion($e);

    expect($criterios->pluck('nombre')->all())->toBe(['Práctica', 'Examen'])
        ->and($criterios->pluck('orden')->all())->toBe([1, 2])
        ->and((float) $criterios[0]->peso)->toBe(60.0);
});

it('rechaza los pesos que no suman exactamente 100,00 sin cambiar nada', function () {
    $e = escenarioEvaluacion(1);
    esquemaEvaluacion($e);

    foreach ([[70, 40], [60, 39.99], [50, 49.99]] as [$a, $b]) {
        expect(fn () => esquemaEvaluacion($e, [['nombre' => 'A', 'peso' => $a], ['nombre' => 'B', 'peso' => $b]]))
            ->toThrow(EvaluacionIncumplida::class);
    }

    expect(CriterioEvaluacion::orderBy('orden')->pluck('nombre')->all())->toBe(['Práctica', 'Examen']);
});

it('acepta pesos con coma decimal que suman 100', function () {
    $e = escenarioEvaluacion(1);

    $criterios = esquemaEvaluacion($e, [
        ['nombre' => 'A', 'peso' => '33,33'], ['nombre' => 'B', 'peso' => '33,33'], ['nombre' => 'C', 'peso' => '33,34'],
    ]);

    expect($criterios)->toHaveCount(3);
});

it('rechaza peso cero, negativo o mayor que 100', function () {
    $e = escenarioEvaluacion(1);

    foreach ([0, -10, 101, 'abc', '12,345'] as $peso) {
        expect(fn () => esquemaEvaluacion($e, [['nombre' => 'A', 'peso' => $peso], ['nombre' => 'B', 'peso' => 50]]))
            ->toThrow(EvaluacionIncumplida::class);
    }
});

it('rechaza nombre vacío, de más de 60 caracteres o repetido', function () {
    $e = escenarioEvaluacion(1);

    expect(fn () => esquemaEvaluacion($e, [['nombre' => '  ', 'peso' => 100]]))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => esquemaEvaluacion($e, [['nombre' => str_repeat('a', 61), 'peso' => 100]]))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => esquemaEvaluacion($e, [['nombre' => 'Examen', 'peso' => 50], ['nombre' => 'EXAMEN', 'peso' => 50]]))->toThrow(EvaluacionIncumplida::class);

    // El máximo de 60 caracteres sí se acepta.
    expect(esquemaEvaluacion($e, [['nombre' => str_repeat('a', 60), 'peso' => 100]]))->toHaveCount(1);
});

it('rechaza un esquema vacío', function () {
    $e = escenarioEvaluacion(1);

    expect(fn () => esquemaEvaluacion($e, []))->toThrow(EvaluacionIncumplida::class, 'al menos un criterio');
});

it('actualiza, agrega y elimina por id y permite intercambiar nombres', function () {
    $e = escenarioEvaluacion(1);
    [$a, $b] = esquemaEvaluacion($e)->all();

    $nuevo = esquemaEvaluacion($e, [
        ['id' => $b->id, 'nombre' => 'Práctica', 'peso' => 30],   // toma el nombre que tenía el otro
        ['id' => $a->id, 'nombre' => 'Examen', 'peso' => 50],
        ['nombre' => 'Tarea', 'peso' => 20],
    ]);

    expect($nuevo->pluck('nombre')->all())->toBe(['Práctica', 'Examen', 'Tarea'])
        ->and($nuevo[0]->id)->toBe($b->id)->and($nuevo[1]->id)->toBe($a->id)
        ->and(CriterioEvaluacion::count())->toBe(3);
});

it('cambiar un peso recalcula el promedio sin tocar las notas', function () {
    $e = escenarioEvaluacion(1);
    [$a, $b] = esquemaEvaluacion($e)->all();
    $alumno = $e['alumnos']->first();
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$alumno->id => [$a->id => '20', $b->id => '10']], $e['admin']);
    $consulta = app(ConsultaNotas::class);

    expect($consulta->promediosDelPar($e['seccion'], $e['curso'], $e['periodo'])[$alumno->id])->toBe(16.0); // 60/40

    esquemaEvaluacion($e, [['id' => $a->id, 'nombre' => 'Práctica', 'peso' => 50], ['id' => $b->id, 'nombre' => 'Examen', 'peso' => 50]]);

    expect($consulta->promediosDelPar($e['seccion'], $e['curso'], $e['periodo'])[$alumno->id])->toBe(15.0)
        ->and(Nota::count())->toBe(2);
});

it('eliminar un criterio borra sus notas', function () {
    $e = escenarioEvaluacion(1);
    [$a, $b] = esquemaEvaluacion($e)->all();
    $alumno = $e['alumnos']->first();
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$alumno->id => [$a->id => '20', $b->id => '10']], $e['admin']);

    esquemaEvaluacion($e, [['id' => $a->id, 'nombre' => 'Práctica', 'peso' => 100]]);

    expect(CriterioEvaluacion::count())->toBe(1)
        ->and(Nota::pluck('criterio_id')->all())->toBe([$a->id]);
});

it('un id de criterio ajeno al par se rechaza', function () {
    $e = escenarioEvaluacion(1);
    $ajeno = CriterioEvaluacion::factory()->create();

    expect(fn () => esquemaEvaluacion($e, [['id' => $ajeno->id, 'nombre' => 'X', 'peso' => 100]]))->toThrow(EvaluacionIncumplida::class);
    expect(CriterioEvaluacion::whereKey($ajeno->id)->exists())->toBeTrue();
});

it('los criterios de un par no afectan a los de otro curso o periodo', function () {
    $e = escenarioEvaluacion(1);
    $otro = CriterioEvaluacion::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id, 'nombre' => 'Otro curso']);

    esquemaEvaluacion($e);
    esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]]);

    expect(CriterioEvaluacion::whereKey($otro->id)->exists())->toBeTrue();
});
