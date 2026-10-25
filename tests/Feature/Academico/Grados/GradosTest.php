<?php

use App\Livewire\Academico\Grados\Index;
use App\Models\Grado;
use App\Models\Nivel;
use Livewire\Livewire;

function adminAcademico()
{
    return usuarioConPermisos(['academico.ver', 'academico.gestionar']);
}

it('crea un grado con nivel, nombre y orden', function () {
    $nivel = Nivel::factory()->create();

    Livewire::actingAs(adminAcademico())->test(Index::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', ' 1°  grado ')->set('orden', '1')
        ->call('guardar')->assertHasNoErrors();

    $grado = Grado::first();
    expect($grado->nombre)->toBe('1° grado')->and($grado->orden)->toBe(1)->and($grado->activo)->toBeTrue();
});

it('edita un grado', function () {
    $grado = Grado::factory()->create(['nombre' => 'Uno', 'orden' => 1]);

    Livewire::actingAs(adminAcademico())->test(Index::class)
        ->call('editar', $grado->id)->assertSet('nombre', 'Uno')
        ->set('nombre', 'Dos')->call('guardar')->assertHasNoErrors();

    expect($grado->fresh()->nombre)->toBe('Dos');
});

it('rechaza nombre u orden repetidos en el mismo nivel, ignorando espacios y mayúsculas', function (string $nombre, string $orden, string $campo) {
    $nivel = Nivel::factory()->create();
    Grado::factory()->for($nivel)->create(['nombre' => '1° Grado', 'orden' => 1]);

    Livewire::actingAs(adminAcademico())->test(Index::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', $nombre)->set('orden', $orden)
        ->call('guardar')->assertHasErrors($campo);

    expect(Grado::count())->toBe(1);
})->with([
    'nombre idéntico' => ['1° Grado', '2', 'nombre'],
    'nombre con espacios extra' => [' 1°   Grado ', '2', 'nombre'],
    'nombre en otra capitalización' => ['1° GRADO', '2', 'nombre'],
    'orden repetido' => ['Otro', '1', 'orden'],
]);

it('permite el mismo nombre y orden en otro nivel', function () {
    $primaria = Nivel::factory()->create();
    $secundaria = Nivel::factory()->create();
    Grado::factory()->for($primaria)->create(['nombre' => '1°', 'orden' => 1]);

    Livewire::actingAs(adminAcademico())->test(Index::class)
        ->set('nivel_id', (string) $secundaria->id)->set('nombre', '1°')->set('orden', '1')
        ->call('guardar')->assertHasNoErrors();

    expect(Grado::count())->toBe(2);
});

it('exige un nivel activo', function () {
    $inactivo = Nivel::factory()->inactivo()->create();

    $c = Livewire::actingAs(adminAcademico())->test(Index::class)
        ->set('nombre', 'X')->set('orden', '1')->call('guardar')->assertHasErrors('nivel_id');
    $c->set('nivel_id', (string) $inactivo->id)->call('guardar')->assertHasErrors('nivel_id');

    expect(Grado::count())->toBe(0);
});

it('filtra por nivel y pagina de 15 en 15', function () {
    $a = Nivel::factory()->create();
    $b = Nivel::factory()->create();
    Grado::factory()->for($a)->count(20)->create();
    Grado::factory()->for($b)->count(2)->create();

    $c = Livewire::actingAs(adminAcademico())->test(Index::class);
    expect($c->viewData('grados')->perPage())->toBe(15)->and($c->viewData('grados')->total())->toBe(22);

    $c->set('filtroNivel', (string) $b->id);
    expect($c->viewData('grados')->total())->toBe(2);
});

it('con solo academico.ver lista pero no modifica (403)', function () {
    $consulta = usuarioConPermisos(['academico.ver']);
    $grado = Grado::factory()->create(['nombre' => 'Visible']);

    $this->actingAs($consulta)->get('/academico/grados')->assertOk()->assertSee('Visible');
    Livewire::actingAs($consulta)->test(Index::class)->set('nombre', 'X')->call('guardar')->assertForbidden();
    Livewire::actingAs($consulta)->test(Index::class)->call('editar', $grado->id)->assertForbidden();
});

it('sin academico.ver responde 403', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/academico/grados')->assertForbidden();
});
