<?php

use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Livewire\Academico\Asignaciones\Index;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Permiso;
use Database\Factories\AsignacionDocenteFactory;
use Livewire\Livewire;

function adminVeTodas()
{
    return usuarioConPermisos(['asignaciones.ver_todas', 'asignaciones.gestionar']);
}

it('al desactivar al docente su asignación se conserva y queda pendiente de reasignar', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(2);
    $pendiente = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    $normal = app(AsignacionService::class)->asignar($seccion, $cursos[1], AsignacionDocenteFactory::docenteElegible());

    $docente->update(['activo' => false]);

    $c = Livewire::actingAs(adminVeTodas())->test(Index::class);
    $filas = $c->viewData('asignaciones')->keyBy('id');
    expect($filas[$pendiente->id]->pendiente_reasignar)->toBeTrue()->and($filas[$normal->id]->pendiente_reasignar)->toBeFalse();
    $c->assertSee('Pendiente de reasignar');

    expect(fn () => app(AsignacionService::class)->asignar($seccion, Curso::factory()->create(), $docente))->toThrow(ReglaAcademicaIncumplida::class);
});

it('si su rol pierde docencia.dictar también queda pendiente y no se le crean nuevas', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(2);
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    $docente->rol->permisos()->detach(Permiso::where('identificador', 'docencia.dictar')->first());

    $c = Livewire::actingAs(adminVeTodas())->test(Index::class);
    expect($c->viewData('asignaciones')->first()->pendiente_reasignar)->toBeTrue();
    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[1], $docente->fresh()))->toThrow(ReglaAcademicaIncumplida::class);
    expect(AsignacionDocente::count())->toBe(1)->and($asignacion->fresh()->usuario_id)->toBe($docente->id);
});

it('el filtro «solo pendientes» muestra únicamente las asignaciones pendientes', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(2);
    $pendiente = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    app(AsignacionService::class)->asignar($seccion, $cursos[1], AsignacionDocenteFactory::docenteElegible());
    $docente->update(['activo' => false]);

    $c = Livewire::actingAs(adminVeTodas())->test(Index::class)->set('soloPendientes', true);

    expect($c->viewData('asignaciones')->pluck('id')->all())->toBe([$pendiente->id]);
});

it('reasignar una asignación pendiente a un docente elegible la resuelve', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    $docente->update(['activo' => false]);
    $nuevo = AsignacionDocenteFactory::docenteElegible();

    app(AsignacionService::class)->reasignar($asignacion, $nuevo);

    expect($asignacion->fresh()->load('usuario.rol.permisos')->pendiente_reasignar)->toBeFalse();
});
