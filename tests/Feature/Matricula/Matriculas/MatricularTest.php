<?php

use App\Matricula\EstadoMatricula;
use App\Matricula\MatriculaIncumplida;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Seccion;
use App\Support\ZonaHoraria;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('matricula a un alumno activo en una sección activa del año vigente', function () {
    ['seccion' => $seccion, 'anio' => $anio] = escenarioMatricula();
    $alumno = Alumno::factory()->create();

    $matricula = app(MatriculaService::class)->matricular($alumno, $seccion);

    expect($matricula->estado)->toBe(EstadoMatricula::Matriculado)
        ->and($matricula->anio_id)->toBe($anio->id)
        ->and($matricula->anio_id)->toBe($seccion->anio_escolar_id)
        ->and($matricula->fecha_matricula->toDateString())->toBe(ZonaHoraria::hoy()); // el día de la institución, no el de UTC
});

it('rechaza al alumno que ya tiene una matrícula activa en el año', function () {
    ['seccion' => $seccion, 'anio' => $anio] = escenarioMatricula();
    $otra = Seccion::factory()->for($seccion->grado)->for($anio, 'anio')->create(['identificador' => 'B']);
    $alumno = Alumno::factory()->create();
    $servicio = app(MatriculaService::class);
    $servicio->matricular($alumno, $seccion);

    expect(fn () => $servicio->matricular($alumno, $otra))->toThrow(MatriculaIncumplida::class, 'ya tiene una matrícula activa');
    expect(Matricula::count())->toBe(1);
});

it('rechaza alumno desactivado, sección desactivada, sección de otro año y falta de año vigente', function () {
    ['seccion' => $seccion, 'anio' => $anio] = escenarioMatricula();
    $servicio = app(MatriculaService::class);

    expect(fn () => $servicio->matricular(Alumno::factory()->inactivo()->create(), $seccion))->toThrow(MatriculaIncumplida::class, 'desactivado');

    $inactiva = Seccion::factory()->inactiva()->for($seccion->grado)->for($anio, 'anio')->create(['identificador' => 'Z']);
    expect(fn () => $servicio->matricular(Alumno::factory()->create(), $inactiva))->toThrow(MatriculaIncumplida::class, 'desactivada');
    expect($inactiva->matriculas()->count())->toBe(0);

    $deOtroAnio = Seccion::factory()->for($seccion->grado)->for(AnioEscolar::factory()->create(), 'anio')->create();
    expect(fn () => $servicio->matricular(Alumno::factory()->create(), $deOtroAnio))->toThrow(MatriculaIncumplida::class, 'año escolar vigente');

    $anio->forceFill(['vigente' => null])->save();
    expect(fn () => $servicio->matricular(Alumno::factory()->create(), $seccion))->toThrow(MatriculaIncumplida::class, 'No hay un año escolar vigente');
});

it('la base impide dos matrículas activas del mismo alumno y año aunque se salte el servicio (concurrencia)', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]);

    expect(fn () => Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]))
        ->toThrow(UniqueConstraintViolationException::class);

    // Una retirada no cuenta como activa: el mismo alumno puede tener otra en el año.
    Matricula::factory()->retirada()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]);
    Matricula::factory()->retirada()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id]);
    expect(Matricula::where('alumno_id', $alumno->id)->count())->toBe(3);
});

it('masiva: matricula a todos o a ninguno e indica la posición del alumno que lo impide', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $servicio = app(MatriculaService::class);
    [$a, $b, $c] = Alumno::factory()->count(3)->create();

    $servicio->matricularVarios($seccion, [$a->id, $b->id]);
    expect(Matricula::count())->toBe(2);

    $d = Alumno::factory()->create();
    try {
        $servicio->matricularVarios($seccion, [$c->id, $a->id, $d->id]);
        $this->fail('Debió lanzar MatriculaIncumplida.');
    } catch (MatriculaIncumplida $e) {
        expect($e->posicion)->toBe(2)
            ->and($e->getMessage())->toContain('No se matriculó a ninguno')->toContain('Alumno 2 de la lista')
            ->not->toContain($a->nombres)->not->toContain($a->numero_documento);
    }

    expect(Matricula::count())->toBe(2)
        ->and(Matricula::where('alumno_id', $c->id)->exists())->toBeFalse()
        ->and(Matricula::where('alumno_id', $d->id)->exists())->toBeFalse();
});

it('masiva exige al menos un alumno y no repite alumnos', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $servicio = app(MatriculaService::class);

    expect(fn () => $servicio->matricularVarios($seccion, []))->toThrow(MatriculaIncumplida::class, 'al menos un alumno');

    $a = Alumno::factory()->create();
    expect($servicio->matricularVarios($seccion, [$a->id, $a->id]))->toHaveCount(1);
});

it('una sección desactivada conserva sus matrículas visibles y no se ofrece para matrículas nuevas', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $matricula = Matricula::factory()->create(['seccion_id' => $seccion->id]);
    $seccion->update(['activo' => false]);

    expect($matricula->fresh()->exists)->toBeTrue()
        ->and(fn () => app(MatriculaService::class)->matricular(Alumno::factory()->create(), $seccion->fresh()))
        ->toThrow(MatriculaIncumplida::class, 'desactivada');
});

it('la columna generada marca el año solo mientras la matrícula está activa', function () {
    ['seccion' => $seccion, 'anio' => $anio] = escenarioMatricula();
    $matricula = Matricula::factory()->create(['seccion_id' => $seccion->id]);

    expect((int) DB::table('matriculas')->where('id', $matricula->id)->value('anio_activa_id'))->toBe($anio->id);

    $matricula->update(['estado' => EstadoMatricula::Retirado]);
    expect(DB::table('matriculas')->where('id', $matricula->id)->value('anio_activa_id'))->toBeNull();
});
