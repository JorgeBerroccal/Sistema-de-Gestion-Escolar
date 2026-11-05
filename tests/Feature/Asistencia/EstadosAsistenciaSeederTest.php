<?php

use App\Models\EstadoAsistencia;
use Database\Seeders\EstadosAsistenciaSeeder;

it('carga los cuatro estados con sus banderas y el vínculo de justificación', function () {
    $this->seed(EstadosAsistenciaSeeder::class);

    $e = EstadoAsistencia::all()->keyBy('codigo');
    expect($e)->toHaveCount(4)
        ->and($e['presente']->cuenta_asistencia)->toBeTrue()
        ->and($e['tarde']->cuenta_asistencia)->toBeTrue()
        ->and($e['falta']->cuenta_falta)->toBeTrue()
        ->and($e['falta_justificada']->cuenta_falta)->toBeFalse()->and($e['falta_justificada']->cuenta_asistencia)->toBeFalse()
        ->and($e['falta']->justificado_id)->toBe($e['falta_justificada']->id)
        ->and($e['falta_justificada']->origenDeJustificacion()->codigo)->toBe('falta');
});

it('es idempotente y no pisa cambios hechos después', function () {
    $this->seed(EstadosAsistenciaSeeder::class);
    EstadoAsistencia::where('codigo', 'tarde')->update(['activo' => false]);

    $this->seed(EstadosAsistenciaSeeder::class);

    expect(EstadoAsistencia::count())->toBe(4)
        ->and(EstadoAsistencia::where('codigo', 'tarde')->value('activo'))->toBeFalse();
});
