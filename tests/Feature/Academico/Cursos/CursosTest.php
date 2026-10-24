<?php

use App\Livewire\Academico\Cursos\Form;
use App\Livewire\Academico\Cursos\Index;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use Livewire\Livewire;

function cursosAdmin()
{
    return usuarioConPermisos(['academico.ver', 'academico.gestionar']);
}

it('crea un curso con nivel y varios grados del nivel', function () {
    $nivel = Nivel::factory()->create();
    $grados = Grado::factory()->for($nivel)->count(3)->create();

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', ' Comunicación ')
        ->set('grados', [(string) $grados[0]->id, (string) $grados[2]->id])
        ->call('guardar')->assertHasNoErrors()->assertRedirect(route('academico.cursos.index'));

    $curso = Curso::first();
    expect($curso->nombre)->toBe('Comunicación')->and($curso->grados->pluck('id')->sort()->values()->all())->toBe([$grados[0]->id, $grados[2]->id]);
});

it('rechaza un grado de otro nivel (validación en servidor)', function () {
    $nivel = Nivel::factory()->create();
    $ajeno = Grado::factory()->create();

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', 'Arte')->set('grados', [(string) $ajeno->id])
        ->call('guardar')->assertHasErrors('grados.0');

    expect(Curso::count())->toBe(0);
});

it('rechaza un curso sin grados', function () {
    $nivel = Nivel::factory()->create();

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', 'Arte')->set('grados', [])
        ->call('guardar')->assertHasErrors('grados');

    expect(Curso::count())->toBe(0);
});

it('nombre único por nivel (sin distinguir mayúsculas ni espacios) y repetible en otro nivel', function () {
    $nivel = Nivel::factory()->create();
    $otro = Nivel::factory()->create();
    $g = Grado::factory()->for($nivel)->create();
    $go = Grado::factory()->for($otro)->create();
    Curso::factory()->for($nivel)->create(['nombre' => 'Matemática']);

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', ' MATEMÁTICA ')->set('grados', [(string) $g->id])
        ->call('guardar')->assertHasErrors('nombre');

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $otro->id)->set('nombre', 'Matemática')->set('grados', [(string) $go->id])
        ->call('guardar')->assertHasNoErrors();

    expect(Curso::count())->toBe(2);
});

it('cambiar el nivel en el formulario limpia los grados elegidos', function () {
    $a = Nivel::factory()->create();
    $b = Nivel::factory()->create();
    $g = Grado::factory()->for($a)->create();

    Livewire::actingAs(cursosAdmin())->test(Form::class)
        ->set('nivel_id', (string) $a->id)->set('grados', [(string) $g->id])
        ->set('nivel_id', (string) $b->id)->assertSet('grados', []);
});

it('edita un curso y reemplaza sus grados', function () {
    $nivel = Nivel::factory()->create();
    [$g1, $g2] = Grado::factory()->for($nivel)->count(2)->create();
    $curso = Curso::factory()->for($nivel)->create(['nombre' => 'Viejo']);
    $curso->grados()->attach($g1);

    Livewire::actingAs(cursosAdmin())->test(Form::class, ['curso' => $curso])
        ->assertSet('nombre', 'Viejo')->assertSet('grados', [(string) $g1->id])
        ->set('nombre', 'Nuevo')->set('grados', [(string) $g2->id])
        ->call('guardar')->assertHasNoErrors();

    expect($curso->fresh()->nombre)->toBe('Nuevo')->and($curso->grados()->pluck('grados.id')->all())->toBe([$g2->id]);
});

it('lista con filtro por nivel y paginación de 15', function () {
    $nivel = Nivel::factory()->create();
    Curso::factory()->for($nivel)->count(20)->create();
    Curso::factory()->count(2)->create();

    $c = Livewire::actingAs(usuarioConPermisos(['academico.ver']))->test(Index::class);
    expect($c->viewData('cursos')->perPage())->toBe(15)->and($c->viewData('cursos')->total())->toBe(22);
    expect($c->set('filtroNivel', (string) $nivel->id)->viewData('cursos')->total())->toBe(20);
});

it('con solo academico.ver lista pero no crea ni edita (403)', function () {
    $consulta = usuarioConPermisos(['academico.ver']);
    $curso = Curso::factory()->create(['nombre' => 'Visible']);

    $this->actingAs($consulta)->get('/academico/cursos')->assertOk()->assertSee('Visible');
    $this->actingAs($consulta)->get('/academico/cursos/crear')->assertForbidden();
    $this->actingAs($consulta)->get("/academico/cursos/{$curso->id}/editar")->assertForbidden();
    Livewire::actingAs($consulta)->test(Form::class)->assertForbidden();
});

it('sin academico.ver responde 403', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/academico/cursos')->assertForbidden();
});

it('el formulario no ofrece grados inactivos, salvo los que el curso ya tiene', function () {
    $nivel = Nivel::factory()->create();
    $activo = Grado::factory()->for($nivel)->create();
    $inactivoLibre = Grado::factory()->inactivo()->for($nivel)->create();
    $inactivoDelCurso = Grado::factory()->inactivo()->for($nivel)->create();
    $curso = Curso::factory()->for($nivel)->create();
    $curso->grados()->attach($inactivoDelCurso);

    $nuevo = Livewire::actingAs(cursosAdmin())->test(Form::class)->set('nivel_id', (string) $nivel->id);
    expect($nuevo->viewData('gradosDelNivel')->pluck('id')->all())->toBe([$activo->id]);

    $edicion = Livewire::actingAs(cursosAdmin())->test(Form::class, ['curso' => $curso]);
    expect($edicion->viewData('gradosDelNivel')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$activo->id, $inactivoDelCurso->id])->sort()->values()->all())
        ->not->toContain($inactivoLibre->id);
});
