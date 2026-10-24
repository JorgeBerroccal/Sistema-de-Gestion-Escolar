<?php

use App\Academico\AsignacionService;
use App\Livewire\Academico\Cursos\Form;
use App\Models\Grado;
use App\Models\Nivel;
use Livewire\Livewire;

it('no se quita un grado en que el curso tiene asignaciones', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $curso = $cursos[0];
    $otroGrado = Grado::factory()->create(['nivel_id' => $curso->nivel_id]);
    $curso->grados()->attach($otroGrado);
    app(AsignacionService::class)->asignar($seccion, $curso, $docente);
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Form::class, ['curso' => $curso])
        ->set('grados', [(string) $otroGrado->id])->call('guardar')->assertHasErrors('grados');

    expect($curso->grados()->pluck('grados.id')->sort()->values()->all())->toBe(collect([$seccion->grado_id, $otroGrado->id])->sort()->values()->all());
});

it('sí se quita un grado en que el curso no tiene asignaciones', function () {
    ['seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $curso = $cursos[0];
    $otroGrado = Grado::factory()->create(['nivel_id' => $curso->nivel_id]);
    $curso->grados()->attach($otroGrado);
    app(AsignacionService::class)->asignar($seccion, $curso, $docente);
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Form::class, ['curso' => $curso])
        ->set('grados', [(string) $seccion->grado_id])->call('guardar')->assertHasNoErrors();

    expect($curso->grados()->pluck('grados.id')->all())->toBe([$seccion->grado_id]);
});

it('no se cambia el nivel de un curso que ya tiene grados asociados', function () {
    ['cursos' => $cursos] = escenarioAsignacion(1);
    $curso = $cursos[0];
    $otroNivel = Nivel::factory()->create();
    $gradoAjeno = Grado::factory()->for($otroNivel)->create();
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Form::class, ['curso' => $curso])
        ->set('nivel_id', (string) $otroNivel->id)->set('grados', [(string) $gradoAjeno->id])
        ->call('guardar')->assertHasErrors('nivel_id');

    expect($curso->fresh()->nivel_id)->not->toBe($otroNivel->id);
});
