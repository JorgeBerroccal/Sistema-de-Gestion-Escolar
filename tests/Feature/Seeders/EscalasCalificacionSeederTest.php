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

it('siembra equivalentes y umbrales como configuración de la institución', function () {
    $this->seed(EscalasCalificacionSeeder::class);

    $literal = EscalaCalificacion::where('codigo', 'literal')->first();
    $vigesimal = EscalaCalificacion::where('codigo', 'vigesimal')->first();

    expect($literal->equivalentes)->toEqual(['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1])
        ->and($literal->umbral_aprobacion)->toBe('B')
        ->and($vigesimal->equivalentes)->toBeNull()
        ->and($vigesimal->umbral_aprobacion)->toBe('11');
});

it('completa las escalas existentes solo si están vacías y no pisa los cambios', function () {
    EscalaCalificacion::create(['codigo' => 'literal', 'nombre' => 'Literal', 'valores' => ['AD', 'A', 'B', 'C']]);
    EscalaCalificacion::create(['codigo' => 'vigesimal', 'nombre' => 'Vigesimal', 'valores' => ['minimo' => 0, 'maximo' => 20], 'umbral_aprobacion' => '13']);

    $this->seed(EscalasCalificacionSeeder::class);

    expect(EscalaCalificacion::where('codigo', 'literal')->first()->equivalentes)->toEqual(['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1])
        ->and(EscalaCalificacion::where('codigo', 'vigesimal')->value('umbral_aprobacion'))->toBe('13');

    EscalaCalificacion::where('codigo', 'literal')->update(['equivalentes' => json_encode(['AD' => 20, 'A' => 17, 'B' => 14, 'C' => 10]), 'umbral_aprobacion' => 'A']);
    $this->seed(EscalasCalificacionSeeder::class);

    expect(EscalaCalificacion::where('codigo', 'literal')->first()->equivalentes['AD'])->toBe(20)
        ->and(EscalaCalificacion::where('codigo', 'literal')->value('umbral_aprobacion'))->toBe('A');
});
