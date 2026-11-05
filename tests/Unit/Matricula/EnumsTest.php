<?php

use App\Matricula\EstadoMatricula;
use App\Matricula\Parentesco;
use App\Matricula\Sexo;

it('solo matriculado es una matrícula activa', function () {
    expect(EstadoMatricula::Matriculado->esActiva())->toBeTrue()
        ->and(EstadoMatricula::Retirado->esActiva())->toBeFalse()
        ->and(EstadoMatricula::Trasladado->esActiva())->toBeFalse()
        ->and(array_column(EstadoMatricula::cases(), 'value'))->toBe(['matriculado', 'retirado', 'trasladado']);
});

it('define los parentescos y el sexo con sus valores', function () {
    expect(array_column(Parentesco::cases(), 'value'))->toBe(['madre', 'padre', 'tutor', 'otro'])
        ->and(Sexo::from('F')->etiqueta())->toBe('Femenino')
        ->and(Sexo::from('M')->etiqueta())->toBe('Masculino')
        ->and(Parentesco::Tutor->etiqueta())->toBe('Tutor');
});
