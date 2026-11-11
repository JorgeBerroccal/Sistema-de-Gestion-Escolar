<?php

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\Nota;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function guardarNotas(array $e, array $celdas, $usuario = null): array
{
    return app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $usuario ?? $e['admin']);
}

it('crea una fila por celda llena y ninguna por celda vacía', function () {
    $e = escenarioEvaluacion(2);
    [$a, $b] = esquemaEvaluacion($e)->all();
    [$ana, $beto] = $e['alumnos']->all();

    $r = guardarNotas($e, [$ana->id => [$a->id => '15', $b->id => ''], $beto->id => [$a->id => '', $b->id => '12,5']]);

    expect($r)->toBe(['guardadas' => 2, 'borradas' => 2])
        ->and(Nota::count())->toBe(2)
        ->and(Nota::where('alumno_id', $beto->id)->first()->valor)->toBe('12.5')
        ->and((float) Nota::where('alumno_id', $beto->id)->first()->valor_numerico)->toBe(12.5);
});

it('reguardar reemplaza sin duplicar y vaciar una celda borra su fila', function () {
    $e = escenarioEvaluacion(1);
    [$a, $b] = esquemaEvaluacion($e)->all();
    $ana = $e['alumnos']->first();

    guardarNotas($e, [$ana->id => [$a->id => '10', $b->id => '10']]);
    guardarNotas($e, [$ana->id => [$a->id => '18', $b->id => '']]);

    expect(Nota::count())->toBe(1)
        ->and(Nota::first()->valor)->toBe('18')
        ->and(Nota::first()->registrado_por)->toBe($e['admin']->id);
});

it('la base impide duplicados y el último guardado prevalece (concurrencia)', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    $ana = $e['alumnos']->first();

    // Dos guardados «casi a la vez»: el segundo reemplaza al primero, sin segunda fila.
    guardarNotas($e, [$ana->id => [$a->id => '11']], $e['admin']);
    guardarNotas($e, [$ana->id => [$a->id => '19']], $e['admin']);

    expect(Nota::where('alumno_id', $ana->id)->count())->toBe(1)->and(Nota::first()->valor)->toBe('19');

    // Aunque algo intente insertar directo, el índice único lo rechaza.
    expect(fn () => Nota::create(['criterio_id' => $a->id, 'alumno_id' => $ana->id, 'valor' => '1', 'valor_numerico' => 1, 'registrado_por' => $e['admin']->id]))
        ->toThrow(QueryException::class);
});

it('es todo o nada: una celda fuera de escala no guarda ninguna y señala fila y columna sin nombres', function () {
    $e = escenarioEvaluacion(2);
    [$a, $b] = esquemaEvaluacion($e)->all();
    [$ana, $beto] = $e['alumnos']->all();
    $ana->update(['apellidos' => 'Aaa Secreto', 'nombres' => 'Reservado']);
    $beto->update(['apellidos' => 'Bbb Secreto', 'nombres' => 'Reservado']);

    try {
        guardarNotas($e, [$ana->id => [$a->id => '15', $b->id => '14'], $beto->id => [$a->id => '12', $b->id => '25']]);
        $fallo = null;
    } catch (EvaluacionIncumplida $fallo) {
    }

    expect($fallo)->not->toBeNull()
        ->and(Nota::count())->toBe(0)
        ->and($fallo->errores)->toHaveCount(1)
        ->and($fallo->errores[0])->toContain('Fila 2, columna 2')
        ->and(implode(' ', $fallo->mensajes()))->not->toContain('Secreto')->not->toContain('Reservado');
});

it('con escala literal rechaza «Z» y acepta las letras de la escala', function () {
    $e = escenarioEvaluacion(1, 'literal');
    [$a, $b] = esquemaEvaluacion($e)->all();
    $ana = $e['alumnos']->first();

    expect(fn () => guardarNotas($e, [$ana->id => [$a->id => 'Z']]))->toThrow(EvaluacionIncumplida::class);

    guardarNotas($e, [$ana->id => [$a->id => 'ad', $b->id => 'B']]);

    expect(Nota::where('criterio_id', $a->id)->first()->valor)->toBe('AD')
        ->and((float) Nota::where('criterio_id', $a->id)->first()->valor_numerico)->toBe(4.0)
        ->and(Nota::count())->toBe(2);
});

it('rechaza un alumno ajeno a la nómina activa o un criterio ajeno al esquema', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    $ana = $e['alumnos']->first();
    $ajeno = Alumno::factory()->create();

    expect(fn () => guardarNotas($e, [$ajeno->id => [$a->id => '10']]))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => guardarNotas($e, [$ana->id => [999999 => '10']]))->toThrow(EvaluacionIncumplida::class)
        ->and(Nota::count())->toBe(0);
});

it('exige criterios definidos antes de guardar notas', function () {
    $e = escenarioEvaluacion(1);

    expect(fn () => guardarNotas($e, [$e['alumnos']->first()->id => [1 => '10']]))->toThrow(EvaluacionIncumplida::class, 'criterios');
});

it('un alumno retirado conserva sus notas y no aparece en la nómina de tablas nuevas', function () {
    $e = escenarioEvaluacion(2);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    [$ana, $beto] = $e['alumnos']->all();
    guardarNotas($e, [$ana->id => [$a->id => '14'], $beto->id => [$a->id => '16']]);

    Matricula::where('alumno_id', $beto->id)->update(['estado' => 'retirado', 'fecha_estado' => now()->toDateString()]);

    expect(app(EvaluacionService::class)->nominaActiva($e['seccion'])->pluck('id')->all())->toBe([$ana->id])
        ->and(Nota::where('alumno_id', $beto->id)->count())->toBe(1)
        ->and(fn () => guardarNotas($e, [$beto->id => [$a->id => '20']]))->toThrow(EvaluacionIncumplida::class);
});

it('un alumno matriculado a mitad de año aparece sin notas anteriores y sin contar como cero', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    $nuevo = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $nuevo->id, 'seccion_id' => $e['seccion']->id, 'fecha_matricula' => now()->toDateString()]);

    $promedios = app(ConsultaNotas::class)->promediosDelPar($e['seccion'], $e['curso'], $e['periodo']);

    expect($promedios)->toHaveKey($nuevo->id)->and($promedios[$nuevo->id])->toBeNull();
});

it('SC-001: 30 alumnos × 5 criterios se registran en una sola operación de guardado', function () {
    $e = escenarioEvaluacion(30);
    $criterios = esquemaEvaluacion($e, collect(range(1, 5))->map(fn ($i) => ['nombre' => "C{$i}", 'peso' => 20])->all());
    $celdas = $e['alumnos']->mapWithKeys(fn ($al) => [$al->id => $criterios->mapWithKeys(fn ($c) => [$c->id => (string) rand(5, 20)])->all()])->all();

    $inserciones = 0;
    DB::listen(function ($consulta) use (&$inserciones) {
        if (str_contains($consulta->sql, 'insert into `notas`')) {
            $inserciones++;
        }
    });

    $r = guardarNotas($e, $celdas);

    expect($r['guardadas'])->toBe(150)->and(Nota::count())->toBe(150)->and($inserciones)->toBe(1);
});

it('las notas decimales del promedio coinciden con el cálculo de la muestra', function () {
    $e = escenarioEvaluacion(2);
    [$a, $b] = esquemaEvaluacion($e)->all();
    [$ana, $beto] = $e['alumnos']->all();
    guardarNotas($e, [$ana->id => [$a->id => '15', $b->id => '10'], $beto->id => [$a->id => '', $b->id => '14']]);

    $promedios = app(ConsultaNotas::class)->promediosDelPar($e['seccion'], $e['curso'], $e['periodo']);

    expect($promedios[$ana->id])->toBe(13.0)   // 15×0,6 + 10×0,4
        ->and($promedios[$beto->id])->toBe(14.0); // solo Examen: pesos renormalizados
});
