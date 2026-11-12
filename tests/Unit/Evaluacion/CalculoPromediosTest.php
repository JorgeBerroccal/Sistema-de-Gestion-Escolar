<?php

use App\Evaluacion\CalculoPromedios;
use App\Evaluacion\EvaluacionIncumplida;

function escalaLiteral(): CalculoPromedios
{
    return new CalculoPromedios(['AD', 'A', 'B', 'C'], ['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1], 'B');
}

function escalaVigesimal(): CalculoPromedios
{
    return new CalculoPromedios(['minimo' => 0, 'maximo' => 20], null, '11');
}

it('promedia ponderando los criterios con nota', function () {
    $promedio = escalaVigesimal()->promedioPonderado([
        ['peso' => 60, 'nota' => 15], ['peso' => 40, 'nota' => 10],
    ]);

    expect($promedio)->toBe(13.0);
});

it('renormaliza los pesos sobre los criterios con nota y no cuenta las vacías como cero', function () {
    $calculo = escalaVigesimal();

    expect($calculo->promedioPonderado([['peso' => 60, 'nota' => 15], ['peso' => 40, 'nota' => null]]))->toBe(15.0)
        ->and($calculo->promedioPonderado([['peso' => 60, 'nota' => null], ['peso' => 40, 'nota' => 10]]))->toBe(10.0)
        ->and($calculo->promedioPonderado([['peso' => 50, 'nota' => ''], ['peso' => 50, 'nota' => null]]))->toBeNull()
        ->and($calculo->promedioPonderado([]))->toBeNull();
});

it('una nota 0 es una nota y sí cuenta', function () {
    expect(escalaVigesimal()->promedioPonderado([['peso' => 50, 'nota' => 0], ['peso' => 50, 'nota' => 20]]))->toBe(10.0);
});

it('redondea a dos decimales con pesos 33,33 / 33,33 / 33,34', function () {
    $promedio = escalaVigesimal()->promedioPonderado([
        ['peso' => 33.33, 'nota' => 11], ['peso' => 33.33, 'nota' => 12], ['peso' => 33.34, 'nota' => 14],
    ]);

    expect($promedio)->toBe(12.33);
});

it('un solo criterio con nota decide el promedio', function () {
    expect(escalaVigesimal()->promedioPonderado([['peso' => 100, 'nota' => 17.5]]))->toBe(17.5);
});

it('el promedio anual es el promedio simple de los periodos existentes', function () {
    $calculo = escalaVigesimal();

    expect($calculo->promedioSimple([12.0, null, 14.0, 16.0]))->toBe(14.0)
        ->and($calculo->promedioSimple([null, null]))->toBeNull()
        ->and($calculo->promedioSimple([]))->toBeNull();
});

it('en escala literal promedia equivalentes y muestra la letra más cercana', function () {
    $calculo = escalaLiteral();

    // AD (4) con peso 50 y C (1) con peso 50 → 2,5: empate entre A (3) y B (2) → el mayor, A.
    $promedio = $calculo->promedioPonderado([['peso' => 50, 'nota' => 4], ['peso' => 50, 'nota' => 1]]);

    expect($promedio)->toBe(2.5)
        ->and($calculo->letraDe(2.5))->toBe('A')
        ->and($calculo->etiqueta($promedio))->toBe('A')
        ->and($calculo->etiqueta(3.6))->toBe('AD')
        ->and($calculo->etiqueta(2.4))->toBe('B')
        ->and($calculo->etiqueta(1.2))->toBe('C')
        ->and($calculo->etiqueta(null))->toBeNull();
});

it('en escala vigesimal la etiqueta es el número con dos decimales', function () {
    expect(escalaVigesimal()->etiqueta(13.0))->toBe('13,00')->and(escalaVigesimal()->etiqueta(12.3))->toBe('12,30');
});

it('valida y normaliza valores literales sin importar mayúsculas ni espacios', function () {
    $calculo = escalaLiteral();

    expect($calculo->normalizar(' ad '))->toBe(['valor' => 'AD', 'numerico' => 4.0])
        ->and($calculo->normalizar('c'))->toBe(['valor' => 'C', 'numerico' => 1.0])
        ->and(fn () => $calculo->normalizar('Z'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar('15'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar(''))->toThrow(EvaluacionIncumplida::class);
});

it('valida y normaliza valores vigesimales con coma o punto', function () {
    $calculo = escalaVigesimal();

    expect($calculo->normalizar('15,5'))->toBe(['valor' => '15.5', 'numerico' => 15.5])
        ->and($calculo->normalizar('15.25'))->toBe(['valor' => '15.25', 'numerico' => 15.25])
        ->and($calculo->normalizar('0'))->toBe(['valor' => '0', 'numerico' => 0.0])
        ->and($calculo->normalizar('20'))->toBe(['valor' => '20', 'numerico' => 20.0])
        ->and(fn () => $calculo->normalizar('25'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar('20,01'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar('-1'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar('15,555'))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $calculo->normalizar('AD'))->toThrow(EvaluacionIncumplida::class);
});

it('el umbral de aprobación viene de la escala: numérico o letra', function () {
    $literal = escalaLiteral();
    $vigesimal = escalaVigesimal();

    expect($vigesimal->aprueba(11.0))->toBeTrue()
        ->and($vigesimal->aprueba(10.99))->toBeFalse()
        ->and($literal->aprueba(2.0))->toBeTrue()   // B
        ->and($literal->aprueba(1.4))->toBeFalse()  // C
        ->and($literal->aprueba(null))->toBeNull();

    // Otro umbral configurado: A en literal, 14 en vigesimal.
    $exigente = new CalculoPromedios(['AD', 'A', 'B', 'C'], ['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1], 'A');
    $otro = new CalculoPromedios(['minimo' => 0, 'maximo' => 20], null, '14');

    expect($exigente->aprueba(2.0))->toBeFalse()->and($exigente->aprueba(3.0))->toBeTrue()
        ->and($otro->aprueba(13.9))->toBeFalse()->and($otro->aprueba(14.0))->toBeTrue();
});

it('una escala nueva con otros códigos y equivalentes funciona sin tocar el cálculo', function () {
    $nueva = new CalculoPromedios(['Logro', 'Proceso', 'Inicio'], ['Logro' => 3, 'Proceso' => 2, 'Inicio' => 1], 'Proceso');

    expect($nueva->normalizar(' logro '))->toBe(['valor' => 'Logro', 'numerico' => 3.0])
        ->and($nueva->etiqueta(1.9))->toBe('Proceso')
        ->and($nueva->aprueba(1.4))->toBeFalse()
        ->and($nueva->aprueba(2.0))->toBeTrue()
        ->and(fn () => $nueva->normalizar('AD'))->toThrow(EvaluacionIncumplida::class);
});

it('los pesos válidos suman exactamente 100,00 y cada uno está en (0, 100]', function () {
    expect(CalculoPromedios::pesosValidos([60, 40]))->toBeTrue()
        ->and(CalculoPromedios::pesosValidos(['33.33', '33.33', '33.34']))->toBeTrue()
        ->and(CalculoPromedios::pesosValidos([100]))->toBeTrue()
        ->and(CalculoPromedios::pesosValidos([70, 40]))->toBeFalse()
        ->and(CalculoPromedios::pesosValidos([60, 39.99]))->toBeFalse()
        ->and(CalculoPromedios::pesosValidos([0, 100]))->toBeFalse()
        ->and(CalculoPromedios::pesosValidos([-10, 110]))->toBeFalse()
        ->and(CalculoPromedios::pesosValidos([]))->toBeFalse();
});
