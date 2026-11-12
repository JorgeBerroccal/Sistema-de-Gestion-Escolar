<?php

use App\Evaluacion\BoletaService;
use App\Support\ZonaHoraria;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

it('la boleta imprime la hora de generación en la zona de la institución, no en UTC', function () {
    $b = escenarioBoleta();
    config(['asistencia.zona_horaria' => 'America/Lima']);
    $instante = Carbon::parse('2026-11-12 02:30:00', 'UTC'); // 21:30 del 11/11 en Lima
    Carbon::setTestNow($instante);
    CarbonImmutable::setTestNow($instante);

    $html = view('boletas.boleta', app(BoletaService::class)->datos($b['ana'], $b['periodo'], true))->render();

    expect($html)->toContain('Documento generado el 11/11/2026 21:30')->not->toContain('12/11/2026 02:30');
});

it('«hoy» de la institución no avanza con el día de UTC', function () {
    config(['asistencia.zona_horaria' => 'America/Lima']);
    $instante = Carbon::parse('2026-11-12 02:30:00', 'UTC');
    Carbon::setTestNow($instante);
    CarbonImmutable::setTestNow($instante);

    expect(ZonaHoraria::hoy())->toBe('2026-11-11')->and(ZonaHoraria::local($instante)->format('d/m/Y H:i'))->toBe('11/11/2026 21:30');

    config(['asistencia.zona_horaria' => 'Asia/Tokyo']);
    expect(ZonaHoraria::hoy())->toBe('2026-11-12');
});
