<?php

use App\Asistencia\ResumenAsistencia;
use App\Models\Asistencia;
use Carbon\CarbonImmutable;

function registrar($seccion, $alumno, string $fecha, string $codigo): Asistencia
{
    return Asistencia::factory()->con($codigo)->create(['seccion_id' => $seccion->id, 'alumno_id' => $alumno->id, 'fecha' => $fecha]);
}

it('cuenta por estado y calcula el porcentaje sin las justificadas ni los días sin registro', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioAsistencia(1);
    $a = $alumnos[0];
    $lunes = CarbonImmutable::parse('2026-06-01');
    foreach (['presente', 'presente', 'tarde', 'falta', 'falta_justificada'] as $i => $codigo) {
        registrar($seccion, $a, $lunes->addDays($i)->toDateString(), $codigo);
    }

    $r = app(ResumenAsistencia::class)->delAlumno($a, $lunes, $lunes->addMonth());

    // 3 asistencias / (3 + 1 falta): la justificada no entra en el denominador.
    expect($r['registrados'])->toBe(5)->and($r['asistencias'])->toBe(3)->and($r['faltas'])->toBe(1)
        ->and($r['porcentaje'])->toBe(75.0)
        ->and($r['por_estado']->pluck('total', 'codigo')->all())->toBe(['presente' => 2, 'tarde' => 1, 'falta' => 1, 'falta_justificada' => 1]);
});

it('sin registros o solo con justificadas no hay dato de porcentaje', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioAsistencia(1);
    $d = CarbonImmutable::parse('2026-06-01');
    $resumen = app(ResumenAsistencia::class);

    expect($resumen->delAlumno($alumnos[0], $d, $d->addMonth()))->toMatchArray(['registrados' => 0, 'porcentaje' => null]);

    registrar($seccion, $alumnos[0], $d->toDateString(), 'falta_justificada');
    expect($resumen->delAlumno($alumnos[0], $d, $d->addMonth()))->toMatchArray(['registrados' => 1, 'porcentaje' => null]);
});

it('filtra por rango y por mes', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioAsistencia(1);
    registrar($seccion, $alumnos[0], '2026-05-29', 'falta');
    registrar($seccion, $alumnos[0], '2026-06-01', 'presente');
    $resumen = app(ResumenAsistencia::class);

    expect($resumen->delAlumno($alumnos[0], CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'))['registrados'])->toBe(1)
        ->and($resumen->porMes($alumnos[0], 2026, 6)->pluck('estado.codigo')->all())->toBe(['presente'])
        ->and($resumen->porMes($alumnos[0], 2026, 5))->toHaveCount(1);
});
