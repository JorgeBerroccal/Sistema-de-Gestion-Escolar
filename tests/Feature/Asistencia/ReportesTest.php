<?php

use App\Asistencia\ResumenAsistencia;
use App\Livewire\Asistencia\Reportes\Alumno as ReporteAlumno;
use App\Livewire\Asistencia\Reportes\Index;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\PeriodoEvaluacion;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function sembrar($seccion, $alumno, array $codigos, string $inicio = '2026-06-01'): void
{
    foreach ($codigos as $i => $codigo) {
        Asistencia::factory()->con($codigo)->create([
            'seccion_id' => $seccion->id, 'alumno_id' => $alumno->id,
            'fecha' => CarbonImmutable::parse($inicio)->addDays($i)->toDateString(),
        ]);
    }
}

it('los totales, el porcentaje y el ranking coinciden con la muestra; las justificadas no cuentan en el ranking', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioAsistencia(3);
    sembrar($seccion, $alumnos[0], ['presente', 'falta', 'falta', 'tarde']);
    sembrar($seccion, $alumnos[1], ['presente', 'falta', 'falta_justificada', 'falta_justificada']);
    sembrar($seccion, $alumnos[2], ['presente', 'presente', 'presente', 'presente']);
    $resumen = app(ResumenAsistencia::class);
    $d = CarbonImmutable::parse('2026-06-01');

    $totales = $resumen->deSeccion($seccion, $d, $d->addMonth());
    $ranking = $resumen->rankingFaltas($seccion, $d, $d->addMonth());

    // 12 registros; asistencias = 2+1+... (a0: 2, a1: 1, a2: 4) = 7; faltas injustificadas = 2 + 1 = 3 → 70 %.
    expect($totales['registrados'])->toBe(12)->and($totales['asistencias'])->toBe(7)->and($totales['faltas'])->toBe(3)
        ->and($totales['porcentaje'])->toBe(70.0)
        ->and($ranking->pluck('alumno_id')->all())->toBe([$alumnos[0]->id, $alumnos[1]->id, $alumnos[2]->id])
        ->and($ranking->pluck('faltas')->map(fn ($f) => (int) $f)->all())->toBe([2, 1, 0])
        ->and((int) $ranking->items()[1]->justificadas)->toBe(2);
});

it('el reporte filtra por periodo de evaluación y por rango libre', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'admin' => $admin, 'anio' => $anio] = escenarioAsistencia(1);
    $periodo = PeriodoEvaluacion::create(['anio_escolar_id' => $anio->id, 'orden' => 1, 'nombre' => 'Bimestre 1', 'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-30']);
    sembrar($seccion, $alumnos[0], ['falta', 'presente'], '2026-06-01');
    sembrar($seccion, $alumnos[0], ['falta'], '2026-08-03');

    $c = Livewire::actingAs($admin)->test(Index::class)->set('seccion', (string) $seccion->id)->set('periodo', (string) $periodo->id);
    $c->assertSet('desde', '2026-06-01')->assertSet('hasta', '2026-06-30')
        ->assertViewHas('resumen', fn ($r) => $r['registrados'] === 2);
    $c->set('periodo', '')->set('desde', '2026-08-01')->set('hasta', '2026-08-31')
        ->assertViewHas('resumen', fn ($r) => $r['registrados'] === 1);
});

it('un rango sin registros muestra «sin datos» y no ceros', function () {
    ['seccion' => $seccion, 'admin' => $admin] = escenarioAsistencia(2);

    Livewire::actingAs($admin)->test(Index::class)->set('seccion', (string) $seccion->id)
        ->assertSee('Sin datos de asistencia en este rango')->assertDontSee('0 %');
});

it('el detalle de un alumno muestra sus días y estados del rango', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'admin' => $admin] = escenarioAsistencia(1);
    sembrar($seccion, $alumnos[0], ['falta', 'presente'], '2026-06-01');

    Livewire::actingAs($admin)->test(ReporteAlumno::class, ['seccion' => $seccion, 'alumno' => $alumnos[0], 'desde' => '2026-06-01', 'hasta' => '2026-06-30'])
        ->assertSee('01/06/2026')->assertSee('Falta')->assertSee('02/06/2026')->assertSee('50 %');
});

it('el detalle rechaza a un alumno que no es de la sección', function () {
    ['seccion' => $seccion, 'admin' => $admin] = escenarioAsistencia(1);

    Livewire::actingAs($admin)->test(ReporteAlumno::class, ['seccion' => $seccion, 'alumno' => Alumno::factory()->create()])->assertForbidden();
});
