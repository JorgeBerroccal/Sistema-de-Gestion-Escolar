<?php

use App\Academico\AnioEscolarService;
use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Models\AnioEscolar;
use Illuminate\Support\Facades\DB;

function consultasFor(callable $accion): array
{
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        if (str_contains(strtolower($consulta->sql), 'for update') && str_contains(strtolower($consulta->sql), 'anios_escolares')) {
            $consultas[] = ['sql' => strtolower($consulta->sql), 'bindings' => $consulta->bindings];
        }
    });
    $accion();

    return $consultas;
}

it('asignar() y marcarVigente() bloquean ambos la fila del año vigente (for update)', function () {
    ['anio' => $anio, 'seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);

    $deAsignar = consultasFor(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente));
    $deMarcar = consultasFor(fn () => app(AnioEscolarService::class)->marcarVigente(AnioEscolar::find($anio->id)));

    expect($deAsignar)->not->toBeEmpty()->and($deMarcar)->not->toBeEmpty();
    // Ambos recorren la fila vigente (columna `vigente`) antes de decidir.
    expect(collect($deAsignar)->contains(fn ($c) => str_contains($c['sql'], 'vigente')))->toBeTrue()
        ->and(collect($deMarcar)->contains(fn ($c) => str_contains($c['sql'], 'vigente')))->toBeTrue();
});

it('tras cambiar el año vigente, asignar en una sección del año anterior se rechaza', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    app(AnioEscolarService::class)->marcarVigente(AnioEscolar::factory()->create());

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'año escolar vigente');
});
