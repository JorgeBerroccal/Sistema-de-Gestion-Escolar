<?php

use App\Models\EscalaCalificacion;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EscalasCalificacionSeeder;

it('crea las dos escalas con sus valores', function () {
    $this->seed(EscalasCalificacionSeeder::class);

    expect(EscalaCalificacion::count())->toBe(2)
        ->and(EscalaCalificacion::where('codigo', 'literal')->first()->valores)->toBe(['AD', 'A', 'B', 'C'])
        ->and(EscalaCalificacion::where('codigo', 'vigesimal')->first()->valores)->toEqual(['minimo' => 0, 'maximo' => 20]);
});

it('es idempotente y no pisa cambios', function () {
    $this->seed(EscalasCalificacionSeeder::class);
    EscalaCalificacion::where('codigo', 'literal')->update(['nombre' => 'Mi escala']);

    $this->seed(EscalasCalificacionSeeder::class);

    expect(EscalaCalificacion::count())->toBe(2)
        ->and(EscalaCalificacion::where('codigo', 'literal')->value('nombre'))->toBe('Mi escala');
});

it('forma parte de la carga base de DatabaseSeeder', function () {
    $this->seed(DatabaseSeeder::class);

    expect(EscalaCalificacion::count())->toBe(2);
});
