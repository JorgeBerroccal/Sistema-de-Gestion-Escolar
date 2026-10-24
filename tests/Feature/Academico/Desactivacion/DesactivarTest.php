<?php

use App\Academico\AsignacionService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Livewire\Academico\AniosEscolares\Index as AniosIndex;
use App\Livewire\Academico\Asignaciones\Form;
use App\Livewire\Academico\Asignaciones\Index as AsignacionesIndex;
use App\Livewire\Academico\Cursos\Index as CursosIndex;
use App\Livewire\Academico\Grados\Index as GradosIndex;
use App\Livewire\Academico\Niveles\Index as NivelesIndex;
use App\Livewire\Academico\Secciones\Index as SeccionesIndex;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

it('desactiva y reactiva cada elemento', function (string $componente, string $modelo) {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $registro = $modelo::factory()->create();

    $c = Livewire::actingAs($admin)->test($componente)->call('desactivar', $registro->id)->assertHasNoErrors();
    expect($registro->fresh()->activo)->toBeFalse();

    $c->call('reactivar', $registro->id);
    expect($registro->fresh()->activo)->toBeTrue();
})->with([
    'año' => [AniosIndex::class, AnioEscolar::class],
    'grado' => [GradosIndex::class, Grado::class],
    'sección' => [SeccionesIndex::class, Seccion::class],
    'curso' => [CursosIndex::class, Curso::class],
    'nivel' => [NivelesIndex::class, Nivel::class],
]);

it('el año vigente no se desactiva ni se elimina', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $vigente = AnioEscolar::factory()->vigente()->create();

    Livewire::actingAs($admin)->test(AniosIndex::class)
        ->call('desactivar', $vigente->id)->assertHasErrors('general')
        ->call('eliminar', $vigente->id)->assertHasErrors('general');

    expect($vigente->fresh()->activo)->toBeTrue()->and(AnioEscolar::find($vigente->id))->not->toBeNull();
});

it('un nivel con grados activos no se desactiva, y sí cuando sus grados están inactivos', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $grado = Grado::factory()->create();
    $nivel = $grado->nivel;

    $c = Livewire::actingAs($admin)->test(NivelesIndex::class)->call('desactivar', $nivel->id)->assertHasErrors('general');
    expect($nivel->fresh()->activo)->toBeTrue();

    $grado->update(['activo' => false]);
    $c->call('desactivar', $nivel->id)->assertHasNoErrors();
    expect($nivel->fresh()->activo)->toBeFalse();
});

it('un elemento inactivo no se ofrece para nuevas asignaciones pero sus asignaciones siguen visibles y marcadas', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(2);
    $existente = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    $admin = usuarioConPermisos(['asignaciones.ver_todas', 'asignaciones.gestionar']);

    $cursos[0]->update(['activo' => false]);
    $cursos[1]->update(['activo' => false]);

    // Sigue visible en el listado.
    $lista = Livewire::actingAs($admin)->test(AsignacionesIndex::class);
    expect($lista->viewData('asignaciones')->pluck('id')->all())->toBe([$existente->id]);

    // El formulario no ofrece cursos inactivos; con la sección inactiva ya no ofrece la sección.
    $form = Livewire::actingAs($admin)->test(Form::class)->set('seccion_id', (string) $seccion->id);
    expect($form->viewData('cursosDisponibles'))->toBeEmpty();

    $seccion->update(['activo' => false]);
    expect(Livewire::actingAs($admin)->test(Form::class)->viewData('secciones'))->toBeEmpty();

    // Y el servicio lo rechaza aunque se llame directamente.
    $seccion->update(['activo' => true]);
    $seccion->grado->update(['activo' => false]);
    expect(fn () => app(AsignacionService::class)->asignar($seccion, $cursos[1], $docente))->toThrow(ReglaAcademicaIncumplida::class, 'desactivada');
    expect(AsignacionDocente::count())->toBe(1);
});

it('desactivar es 403 sin academico.gestionar', function () {
    $grado = Grado::factory()->create();

    Livewire::actingAs(usuarioConPermisos(['academico.ver']))->test(GradosIndex::class)->call('desactivar', $grado->id)->assertForbidden();

    expect($grado->fresh()->activo)->toBeTrue();
});
