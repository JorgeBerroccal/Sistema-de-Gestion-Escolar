<?php

use App\Livewire\Academico\AniosEscolares\Index as AniosIndex;
use App\Livewire\Academico\Cursos\Index as CursosIndex;
use App\Livewire\Academico\Grados\Index as GradosIndex;
use App\Livewire\Academico\Niveles\Index as NivelesIndex;
use App\Livewire\Academico\Secciones\Index as SeccionesIndex;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Nivel;
use App\Models\Seccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function gestorDeEstado()
{
    return usuarioConPermisos(['academico.ver', 'academico.gestionar']);
}

it('elimina elementos sin dependientes', function () {
    $admin = gestorDeEstado();
    $anio = AnioEscolar::factory()->create();
    $grado = Grado::factory()->create();
    $seccion = Seccion::factory()->create();
    $curso = Curso::factory()->create();
    $nivel = Nivel::factory()->create();

    Livewire::actingAs($admin)->test(AniosIndex::class)->call('eliminar', $anio->id)->assertHasNoErrors();
    Livewire::actingAs($admin)->test(SeccionesIndex::class)->call('eliminar', $seccion->id)->assertHasNoErrors();
    Livewire::actingAs($admin)->test(CursosIndex::class)->call('eliminar', $curso->id)->assertHasNoErrors();
    Livewire::actingAs($admin)->test(GradosIndex::class)->call('eliminar', $grado->id)->assertHasNoErrors();
    Livewire::actingAs($admin)->test(NivelesIndex::class)->call('eliminar', $nivel->id)->assertHasNoErrors();

    expect(AnioEscolar::find($anio->id))->toBeNull()
        ->and(Seccion::find($seccion->id))->toBeNull()
        ->and(Curso::find($curso->id))->toBeNull()
        ->and(Grado::find($grado->id))->toBeNull()
        ->and(Nivel::find($nivel->id))->toBeNull();
});

it('impide eliminar lo que tiene dependientes y ofrece desactivar', function () {
    $admin = gestorDeEstado();
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    AsignacionDocente::create(['seccion_id' => $seccion->id, 'curso_id' => $cursos[0]->id, 'usuario_id' => $docente->id]);
    $grado = $seccion->grado;
    $anioConSeccion = $seccion->anio->forceFill(['vigente' => null]);
    $anioConSeccion->save();
    $nivel = $grado->nivel;

    Livewire::actingAs($admin)->test(SeccionesIndex::class)->call('eliminar', $seccion->id)->assertHasErrors('general');
    Livewire::actingAs($admin)->test(CursosIndex::class)->call('eliminar', $cursos[0]->id)->assertHasErrors('general');
    Livewire::actingAs($admin)->test(GradosIndex::class)->call('eliminar', $grado->id)->assertHasErrors('general');
    Livewire::actingAs($admin)->test(AniosIndex::class)->call('eliminar', $anioConSeccion->id)->assertHasErrors('general');
    Livewire::actingAs($admin)->test(NivelesIndex::class)->call('eliminar', $nivel->id)->assertHasErrors('general');

    expect(Seccion::find($seccion->id))->not->toBeNull()
        ->and(Curso::find($cursos[0]->id))->not->toBeNull()
        ->and(Grado::find($grado->id))->not->toBeNull()
        ->and(AnioEscolar::find($anioConSeccion->id))->not->toBeNull()
        ->and(Nivel::find($nivel->id))->not->toBeNull();
});

it('un nivel atendido por la institución no se elimina', function () {
    $nivel = Nivel::factory()->create();
    Institucion::factory()->create()->niveles()->attach($nivel);

    Livewire::actingAs(gestorDeEstado())->test(NivelesIndex::class)->call('eliminar', $nivel->id)->assertHasErrors('general');

    expect(Nivel::find($nivel->id))->not->toBeNull();
});

it('la interfaz ofrece Eliminar solo cuando no hay dependientes', function () {
    $admin = gestorDeEstado();
    $libre = Seccion::factory()->create(['identificador' => 'LIBRE']);
    ['seccion' => $conAsignacion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $conAsignacion->update(['identificador' => 'OCUPADA']);
    AsignacionDocente::create(['seccion_id' => $conAsignacion->id, 'curso_id' => $cursos[0]->id, 'usuario_id' => $docente->id]);

    Livewire::actingAs($admin)->test(SeccionesIndex::class)
        ->assertSeeHtml('wire:click="eliminar('.$libre->id.')"')
        ->assertDontSeeHtml('wire:click="eliminar('.$conAsignacion->id.')"')
        ->assertSeeHtml('wire:click="desactivar('.$conAsignacion->id.')"');
});

it('la base rechaza el borrado aunque el código no lo impidiera (restrictOnDelete)', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    AsignacionDocente::create(['seccion_id' => $seccion->id, 'curso_id' => $cursos[0]->id, 'usuario_id' => $docente->id]);

    foreach ([
        ['secciones', $seccion->id],
        ['cursos', $cursos[0]->id],
        ['grados', $seccion->grado_id],
        ['anios_escolares', $seccion->anio_escolar_id],
        ['niveles', $seccion->grado->nivel_id],
        ['usuarios', $docente->id],
    ] as [$tabla, $id]) {
        expect(fn () => DB::transaction(fn () => DB::table($tabla)->where('id', $id)->delete()))->toThrow(QueryException::class);
    }

    expect(AsignacionDocente::count())->toBe(1);
});

it('eliminar es 403 sin academico.gestionar', function () {
    $nivel = Nivel::factory()->create();

    Livewire::actingAs(usuarioConPermisos(['academico.ver']))->test(NivelesIndex::class)->call('eliminar', $nivel->id)->assertForbidden();

    expect(Nivel::find($nivel->id))->not->toBeNull();
});
