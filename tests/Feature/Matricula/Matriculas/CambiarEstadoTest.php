<?php

use App\Matricula\AlumnoService;
use App\Matricula\EstadoMatricula;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\Matricula;
use Carbon\Carbon;

function matriculaActiva(): Matricula
{
    ['seccion' => $seccion] = escenarioMatricula();

    return Matricula::factory()->create(['seccion_id' => $seccion->id, 'fecha_matricula' => '2026-03-01']);
}

it('retira con fecha y motivo y deja de ser activa', function () {
    $matricula = matriculaActiva();

    $r = app(MatriculaService::class)->retirar($matricula, Carbon::parse('2026-05-10'), '  Cambio   de domicilio ');

    expect($r->estado)->toBe(EstadoMatricula::Retirado)
        ->and($r->fecha_estado->toDateString())->toBe('2026-05-10')
        ->and($r->motivo)->toBe('Cambio de domicilio')
        ->and(Matricula::activas()->count())->toBe(0);
});

it('traslada sin motivo', function () {
    $matricula = matriculaActiva();

    $r = app(MatriculaService::class)->trasladar($matricula, Carbon::parse('2026-06-01'));

    expect($r->estado)->toBe(EstadoMatricula::Trasladado)->and($r->motivo)->toBeNull();
});

it('rechaza una fecha anterior a la de la matrícula y un motivo demasiado largo', function () {
    $matricula = matriculaActiva();
    $servicio = app(MatriculaService::class);

    expect(fn () => $servicio->retirar($matricula, Carbon::parse('2026-02-28')))->toThrow(MatriculaIncumplida::class, 'anterior');
    expect(fn () => $servicio->retirar($matricula, Carbon::parse('2026-05-10'), str_repeat('x', 256)))->toThrow(MatriculaIncumplida::class, '255');
    expect($matricula->fresh()->estado)->toBe(EstadoMatricula::Matriculado);
});

it('solo cambia el estado de una matrícula activa', function () {
    $matricula = matriculaActiva();
    $servicio = app(MatriculaService::class);
    $servicio->retirar($matricula, Carbon::parse('2026-05-10'));

    expect(fn () => $servicio->trasladar($matricula->fresh(), Carbon::parse('2026-05-11')))->toThrow(MatriculaIncumplida::class, 'matrícula activa');
});

it('corrige un retiro hecho por error si no hay otra activa en el año', function () {
    $matricula = matriculaActiva();
    $servicio = app(MatriculaService::class);
    $servicio->retirar($matricula, Carbon::parse('2026-05-10'), 'Error');

    $r = $servicio->corregir($matricula->fresh());

    expect($r->estado)->toBe(EstadoMatricula::Matriculado)->and($r->fecha_estado)->toBeNull()->and($r->motivo)->toBeNull();
    expect(fn () => $servicio->corregir($r))->toThrow(MatriculaIncumplida::class, 'ya está activa');
});

it('no corrige si el alumno ya tiene otra matrícula activa ese año', function () {
    $matricula = matriculaActiva();
    $servicio = app(MatriculaService::class);
    $servicio->retirar($matricula, Carbon::parse('2026-05-10'));
    $servicio->matricular($matricula->alumno, $matricula->seccion);

    expect(fn () => $servicio->corregir($matricula->fresh()))->toThrow(MatriculaIncumplida::class, 'otra matrícula activa');
    expect($matricula->fresh()->estado)->toBe(EstadoMatricula::Retirado);
});

it('un alumno retirado vuelve a matricularse en el mismo año y conserva el historial', function () {
    $matricula = matriculaActiva();
    $servicio = app(MatriculaService::class);
    $servicio->retirar($matricula, Carbon::parse('2026-05-10'));

    $nueva = $servicio->matricular($matricula->alumno, $matricula->seccion);

    expect($nueva->id)->not->toBe($matricula->id)
        ->and(Matricula::where('alumno_id', $matricula->alumno_id)->count())->toBe(2)
        ->and($matricula->fresh()->estado)->toBe(EstadoMatricula::Retirado);
});

it('las matrículas nunca se eliminan desde el servicio ni la interfaz (solo cambian de estado)', function () {
    expect(method_exists(MatriculaService::class, 'eliminar'))->toBeFalse();
});

it('un alumno con matrículas no se elimina aunque estén retiradas (SC-004)', function () {
    $matricula = matriculaActiva();
    app(MatriculaService::class)->retirar($matricula, Carbon::parse('2026-05-10'));

    expect(fn () => app(AlumnoService::class)->eliminar(Alumno::find($matricula->alumno_id)))->toThrow(MatriculaIncumplida::class);
});
