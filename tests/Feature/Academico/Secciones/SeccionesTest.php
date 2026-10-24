<?php

use App\Livewire\Academico\Secciones\Index;
use App\Models\AnioEscolar;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

function gestorAcademico()
{
    return usuarioConPermisos(['academico.ver', 'academico.gestionar']);
}

it('crea varias secciones A, B y C de un grado y año', function () {
    $grado = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'a, B ,c')
        ->call('guardar')->assertHasNoErrors();

    expect(Seccion::orderBy('identificador')->pluck('identificador')->all())->toBe(['A', 'B', 'C']);
});

it('permite cantidad distinta de secciones en otro grado', function () {
    $anio = AnioEscolar::factory()->create();
    $g1 = Grado::factory()->create();
    $g2 = Grado::factory()->create();
    Seccion::factory()->for($g1)->for($anio, 'anio')->create(['identificador' => 'A']);
    Seccion::factory()->for($g1)->for($anio, 'anio')->create(['identificador' => 'B']);

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $g2->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'A')
        ->call('guardar')->assertHasNoErrors();

    expect(Seccion::where('grado_id', $g1->id)->count())->toBe(2)->and(Seccion::where('grado_id', $g2->id)->count())->toBe(1);
});

it('rechaza un identificador repetido en el mismo grado y año (A / a)', function () {
    $grado = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();
    Seccion::factory()->for($grado)->for($anio, 'anio')->create(['identificador' => 'A']);

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'a')
        ->call('guardar')->assertHasErrors('identificador');

    expect(Seccion::count())->toBe(1);
});

it('crearVarias es todo o nada: si una falla no se crea ninguna', function () {
    $grado = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();
    Seccion::factory()->for($grado)->for($anio, 'anio')->create(['identificador' => 'B']);

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'A, B, C')
        ->call('guardar')->assertHasErrors('identificador');

    expect(Seccion::count())->toBe(1);
});

it('rechaza identificadores repetidos dentro de la misma lista', function () {
    $grado = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'A, a')
        ->call('guardar')->assertHasErrors('identificador');

    expect(Seccion::count())->toBe(0);
});

it('permite la misma letra en otro año o en otro grado', function () {
    $grado = Grado::factory()->create();
    $otroGrado = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();
    $otroAnio = AnioEscolar::factory()->create();
    Seccion::factory()->for($grado)->for($anio, 'anio')->create(['identificador' => 'A']);

    $c = Livewire::actingAs(gestorAcademico())->test(Index::class);
    $c->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $otroAnio->id)->set('identificador', 'A')->call('guardar')->assertHasNoErrors();
    $c->set('grado_id', (string) $otroGrado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'A')->call('guardar')->assertHasNoErrors();

    expect(Seccion::count())->toBe(3);
});

it('exige grado y año activos y un identificador', function () {
    $grado = Grado::factory()->inactivo()->create();
    $anio = AnioEscolar::factory()->inactivo()->create();

    $c = Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'A')
        ->call('guardar')->assertHasErrors(['grado_id', 'anio_escolar_id']);

    $activo = Grado::factory()->create();
    $anioActivo = AnioEscolar::factory()->create();
    $c->set('grado_id', (string) $activo->id)->set('anio_escolar_id', (string) $anioActivo->id)->set('identificador', ' , ')
        ->call('guardar')->assertHasErrors('identificador');

    expect(Seccion::count())->toBe(0);
});

it('edita una sección', function () {
    $seccion = Seccion::factory()->create(['identificador' => 'A']);

    Livewire::actingAs(gestorAcademico())->test(Index::class)
        ->call('editar', $seccion->id)->assertSet('identificador', 'A')
        ->set('identificador', 'z')->call('guardar')->assertHasNoErrors();

    expect($seccion->fresh()->identificador)->toBe('Z');
});

it('filtra por año, nivel y grado y pagina de 15 en 15', function () {
    $nivel = Nivel::factory()->create();
    $g1 = Grado::factory()->for($nivel)->create();
    $g2 = Grado::factory()->create();
    $anio = AnioEscolar::factory()->create();
    Seccion::factory()->count(20)->for($g1)->for($anio, 'anio')->create();
    Seccion::factory()->count(2)->for($g2)->create();

    $c = Livewire::actingAs(usuarioConPermisos(['academico.ver']))->test(Index::class);
    expect($c->viewData('secciones')->perPage())->toBe(15)->and($c->viewData('secciones')->total())->toBe(22);

    expect($c->set('filtroAnio', (string) $anio->id)->viewData('secciones')->total())->toBe(20);
    $c->set('filtroAnio', '');
    expect($c->set('filtroNivel', (string) $nivel->id)->viewData('secciones')->total())->toBe(20);
    $c->set('filtroNivel', '');
    expect($c->set('filtroGrado', (string) $g2->id)->viewData('secciones')->total())->toBe(2);
});

it('con solo academico.ver lista pero no modifica (403)', function () {
    $consulta = usuarioConPermisos(['academico.ver']);
    $seccion = Seccion::factory()->create();

    $this->actingAs($consulta)->get('/academico/secciones')->assertOk();
    Livewire::actingAs($consulta)->test(Index::class)->set('identificador', 'A')->call('guardar')->assertForbidden();
    Livewire::actingAs($consulta)->test(Index::class)->call('editar', $seccion->id)->assertForbidden();
});

it('sin academico.ver responde 403', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/academico/secciones')->assertForbidden();
});
