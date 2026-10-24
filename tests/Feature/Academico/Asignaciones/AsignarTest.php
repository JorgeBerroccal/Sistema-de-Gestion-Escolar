<?php

use App\Academico\AnioEscolarService;
use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Livewire\Academico\Asignaciones\Form;
use App\Livewire\Academico\Asignaciones\Index;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Seccion;
use Database\Factories\AsignacionDocenteFactory;
use Livewire\Livewire;

function gestorAsignaciones()
{
    return usuarioConPermisos(['asignaciones.ver_todas', 'asignaciones.gestionar']);
}

it('asigna un docente a un curso de una sección del año vigente', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();

    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    expect($asignacion->exists)->toBeTrue()
        ->and($asignacion->usuario_id)->toBe($docente->id)
        ->and($asignacion->seccion_id)->toBe($seccion->id)
        ->and($asignacion->curso_id)->toBe($cursos[0]->id);
});

it('rechaza un curso ya asignado en la sección', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $servicio = app(AsignacionService::class);
    $servicio->asignar($seccion, $cursos[0], $docente);

    expect(fn () => $servicio->asignar($seccion, $cursos[0], AsignacionDocenteFactory::docenteElegible()))
        ->toThrow(ReglaAcademicaIncumplida::class, 'ya tiene docente');
    expect(AsignacionDocente::count())->toBe(1);
});

it('rechaza un docente inactivo', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $docente->update(['activo' => false]);

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'no puede ser docente');
});

it('rechaza un usuario cuyo rol no tiene docencia.dictar', function () {
    ['seccion' => $seccion, 'cursos' => $cursos] = escenarioAsignacion();

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], usuarioConPermisos(['usuarios.ver'])))
        ->toThrow(ReglaAcademicaIncumplida::class, 'no puede ser docente');
});

it('rechaza una sección de un año que no es el vigente', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $otroAnio = AnioEscolar::factory()->create();
    $seccion->update(['anio_escolar_id' => $otroAnio->id]);

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'año escolar vigente');
});

it('rechaza asignar cuando no hay año vigente', function () {
    ['anio' => $anio, 'seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $anio->forceFill(['vigente' => null])->save();

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'No hay un año escolar vigente');
});

it('rechaza un curso que no se dicta en el grado de la sección', function () {
    ['seccion' => $seccion, 'docente' => $docente] = escenarioAsignacion();
    $ajeno = Curso::factory()->create();

    expect(fn () => app(AsignacionService::class)->asignar($seccion, $ajeno, $docente))->toThrow(ReglaAcademicaIncumplida::class, 'no se dicta en el grado');
});

it('rechaza sección o curso inactivos', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $servicio = app(AsignacionService::class);

    $cursos[0]->update(['activo' => false]);
    expect(fn () => $servicio->asignar($seccion, $cursos[0], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'desactivado');

    $seccion->update(['activo' => false]);
    expect(fn () => $servicio->asignar($seccion, $cursos[1], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'desactivada');
    expect(AsignacionDocente::count())->toBe(0);
});

it('permite que un docente tenga varias secciones', function () {
    ['anio' => $anio, 'seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $otra = Seccion::factory()->for($seccion->grado)->for($anio, 'anio')->create(['identificador' => 'B']);
    $servicio = app(AsignacionService::class);

    $servicio->asignar($seccion, $cursos[0], $docente);
    $servicio->asignar($otra, $cursos[0], $docente);

    expect(AsignacionDocente::where('usuario_id', $docente->id)->count())->toBe(2);
});

it('reasignar cambia solo el docente', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $nuevo = AsignacionDocenteFactory::docenteElegible();
    $servicio = app(AsignacionService::class);
    $asignacion = $servicio->asignar($seccion, $cursos[0], $docente);

    $servicio->reasignar($asignacion, $nuevo);

    $asignacion->refresh();
    expect($asignacion->usuario_id)->toBe($nuevo->id)->and($asignacion->seccion_id)->toBe($seccion->id)->and($asignacion->curso_id)->toBe($cursos[0]->id);
});

it('reasignar rechaza un docente no elegible', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $servicio = app(AsignacionService::class);
    $asignacion = $servicio->asignar($seccion, $cursos[0], $docente);

    expect(fn () => $servicio->reasignar($asignacion, usuarioConPermisos([])))->toThrow(ReglaAcademicaIncumplida::class);
    expect($asignacion->fresh()->usuario_id)->toBe($docente->id);
});

it('quitar: permitido con asignaciones.gestionar en el año vigente', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    Livewire::actingAs(gestorAsignaciones())->test(Index::class)->call('quitar', $asignacion->id)->assertHasNoErrors();

    expect(AsignacionDocente::count())->toBe(0);
});

it('quitar: rechazado en una sección de un año anterior', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    AnioEscolar::factory()->create()->forceFill(['vigente' => null])->save();
    app(AnioEscolarService::class)->marcarVigente(AnioEscolar::factory()->create());

    Livewire::actingAs(gestorAsignaciones())->test(Index::class)->call('quitar', $asignacion->id)->assertHasErrors('general');

    expect(AsignacionDocente::count())->toBe(1);
});

it('quitar: 403 sin asignaciones.gestionar', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);

    Livewire::actingAs(usuarioConPermisos(['asignaciones.ver_todas']))->test(Index::class)->call('quitar', $asignacion->id)->assertForbidden();

    expect(AsignacionDocente::count())->toBe(1);
});

it('el formulario asigna varios cursos y reasigna', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion();
    $nuevo = AsignacionDocenteFactory::docenteElegible();

    Livewire::actingAs(gestorAsignaciones())->test(Form::class)
        ->set('seccion_id', (string) $seccion->id)->set('cursos', [(string) $cursos[0]->id, (string) $cursos[1]->id])->set('docente_id', (string) $docente->id)
        ->call('guardar')->assertHasNoErrors()->assertRedirect(route('academico.asignaciones.index'));

    expect(AsignacionDocente::count())->toBe(2);

    $asignacion = AsignacionDocente::first();
    Livewire::actingAs(gestorAsignaciones())->test(Form::class, ['reasignando' => $asignacion->id])
        ->set('docente_id', (string) $nuevo->id)->call('guardar')->assertHasNoErrors();

    expect($asignacion->fresh()->usuario_id)->toBe($nuevo->id);
});

it('el formulario exige sección, cursos y docente, y muestra los errores de regla', function () {
    ['seccion' => $seccion, 'cursos' => $cursos] = escenarioAsignacion();

    Livewire::actingAs(gestorAsignaciones())->test(Form::class)
        ->call('guardar')->assertHasErrors(['seccion_id', 'cursos', 'docente_id']);

    Livewire::actingAs(gestorAsignaciones())->test(Form::class)
        ->set('seccion_id', (string) $seccion->id)->set('cursos', [(string) $cursos[0]->id])->set('docente_id', (string) usuarioConPermisos([])->id)
        ->call('guardar')->assertHasErrors('general');

    expect(AsignacionDocente::count())->toBe(0);
});

it('asignar es 403 sin asignaciones.gestionar', function () {
    $consulta = usuarioConPermisos(['asignaciones.ver_todas']);

    $this->actingAs($consulta)->get('/academico/asignaciones/crear')->assertForbidden();
    Livewire::actingAs($consulta)->test(Form::class)->assertForbidden();
});
