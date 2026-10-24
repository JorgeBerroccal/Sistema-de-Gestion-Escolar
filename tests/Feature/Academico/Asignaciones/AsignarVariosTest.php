<?php

use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AsignacionDocente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('asigna tres cursos de una sección en una sola operación', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(3);

    $creadas = app(AsignacionService::class)->asignarVarios($seccion, $cursos->pluck('id')->all(), $docente);

    expect($creadas)->toHaveCount(3)->and(AsignacionDocente::where('usuario_id', $docente->id)->count())->toBe(3);
});

it('es todo o nada: si un curso es inválido no se crea ninguno y el error nombra el curso', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(3);
    $cursos[2]->update(['activo' => false]);

    try {
        app(AsignacionService::class)->asignarVarios($seccion, $cursos->pluck('id')->all(), $docente);
        $this->fail('Debió lanzar ReglaAcademicaIncumplida.');
    } catch (ReglaAcademicaIncumplida $e) {
        expect($e->getMessage())->toContain($cursos[2]->nombre)->and($e->cursoId)->toBe($cursos[2]->id);
    }

    expect(AsignacionDocente::count())->toBe(0);
});

it('un curso ya asignado también deja la operación sin efecto', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(3);
    AsignacionDocente::factory()->create(['seccion_id' => $seccion->id, 'curso_id' => $cursos[1]->id]);

    expect(fn () => app(AsignacionService::class)->asignarVarios($seccion, $cursos->pluck('id')->all(), $docente))
        ->toThrow(ReglaAcademicaIncumplida::class, $cursos[1]->nombre);

    expect(AsignacionDocente::count())->toBe(1);
});

it('exige al menos un curso', function () {
    ['seccion' => $seccion, 'docente' => $docente] = escenarioAsignacion(1);

    expect(fn () => app(AsignacionService::class)->asignarVarios($seccion, [], $docente))->toThrow(ReglaAcademicaIncumplida::class);
});

it('concurrencia (a): el índice único impide la misma pareja sección-curso', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $fila = ['seccion_id' => $seccion->id, 'curso_id' => $cursos[0]->id, 'usuario_id' => $docente->id];
    AsignacionDocente::create($fila);

    expect(fn () => DB::transaction(fn () => AsignacionDocente::create($fila)))->toThrow(QueryException::class);
    expect(AsignacionDocente::count())->toBe(1);
});

it('concurrencia (a): el servicio traduce la violación del índice a un mensaje de validación, no a un 500', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);

    // Simula la carrera: otra solicitud inserta la pareja justo después de la comprobación previa.
    $insertada = false;
    AsignacionDocente::creating(function () use (&$insertada, $seccion, $cursos, $docente) {
        if (! $insertada) {
            $insertada = true;
            DB::table('asignaciones_docentes')->insert([
                'seccion_id' => $seccion->id, 'curso_id' => $cursos[0]->id, 'usuario_id' => $docente->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente))
        ->toThrow(ReglaAcademicaIncumplida::class, 'ya tiene docente');

    AsignacionDocente::flushEventListeners();
});

it('concurrencia (b): el servicio bloquea con for update el año vigente y la sección', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        if (str_contains(strtolower($consulta->sql), 'for update')) {
            $consultas[] = strtolower($consulta->sql);
        }
    });

    app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    expect(collect($consultas)->contains(fn ($sql) => str_contains($sql, 'anios_escolares')))->toBeTrue()
        ->and(collect($consultas)->contains(fn ($sql) => str_contains($sql, '`secciones`')))->toBeTrue();
});
