<?php

use App\Evaluacion\BoletaService;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Support\Formato;
use Database\Seeders\EstadosAsistenciaSeeder;

it('Formato usa coma decimal y recorta ceros', function () {
    expect(Formato::decimal(13, 2))->toBe('13,00')->and(Formato::decimal(1234.5, 1))->toBe('1234,5')
        ->and(Formato::recortado('60.00'))->toBe('60')->and(Formato::recortado('15.50'))->toBe('15,5')
        ->and(Formato::recortado(87.5))->toBe('87,5')->and(Formato::recortado(50.0))->toBe('50')
        ->and(Formato::recortado('AD'))->toBe('AD')->and(Formato::recortado(null))->toBe('0');
});

it('la boleta imprime el porcentaje de asistencia y las notas con coma decimal', function () {
    $b = escenarioBoleta();
    (new EstadosAsistenciaSeeder)->run();
    $presente = EstadoAsistencia::where('codigo', 'presente')->first();
    $falta = EstadoAsistencia::where('codigo', 'falta')->first();

    foreach (range(0, 7) as $dia) { // 7 presentes y 1 falta = 87,5 %
        Asistencia::factory()->create([
            'seccion_id' => $b['seccion']->id, 'alumno_id' => $b['ana']->id, 'estado_id' => $dia === 7 ? $falta->id : $presente->id,
            'fecha' => $b['periodo']->fecha_inicio->copy()->addDays($dia + 7)->toDateString(),
        ]);
    }

    $html = view('boletas.boleta', app(BoletaService::class)->datos($b['ana'], $b['periodo'], true))->render();

    expect($html)->toContain('87,5 %')->not->toContain('87.5')->toContain('13,00')->toContain('(60 %)');
});
