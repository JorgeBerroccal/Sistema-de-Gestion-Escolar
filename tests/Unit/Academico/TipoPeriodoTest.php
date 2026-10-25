<?php

use App\Academico\TipoPeriodo;

it('un año con bimestres tiene 4 periodos y con trimestres 3', function () {
    expect(TipoPeriodo::Bimestre->cantidadPeriodos())->toBe(4)
        ->and(TipoPeriodo::Trimestre->cantidadPeriodos())->toBe(3);
});

it('propone los nombres de los periodos empezando en 1', function () {
    expect(TipoPeriodo::Bimestre->nombresSugeridos())->toBe([1 => 'I Bimestre', 2 => 'II Bimestre', 3 => 'III Bimestre', 4 => 'IV Bimestre'])
        ->and(TipoPeriodo::Trimestre->nombresSugeridos())->toBe([1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre']);
});

it('se crea desde su valor guardado y rechaza otros', function () {
    expect(TipoPeriodo::from('bimestre'))->toBe(TipoPeriodo::Bimestre)
        ->and(TipoPeriodo::tryFrom('semestre'))->toBeNull();
});
