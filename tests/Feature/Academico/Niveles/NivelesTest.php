<?php

use App\Livewire\Academico\Niveles\Index;
use App\Models\Nivel;
use Livewire\Livewire;

it('crea un nivel con nombre y orden', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Index::class)
        ->set('nombre', '  Educación   Inicial ')->set('orden', '1')->call('guardar')->assertHasNoErrors();

    $nivel = Nivel::first();
    expect($nivel->nombre)->toBe('Educación Inicial')->and($nivel->orden)->toBe(1)->and($nivel->activo)->toBeTrue();
});

it('edita un nivel existente', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $nivel = Nivel::factory()->create(['nombre' => 'Uno', 'orden' => 1]);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('editar', $nivel->id)->assertSet('nombre', 'Uno')
        ->set('nombre', 'Dos')->call('guardar')->assertHasNoErrors();

    expect($nivel->fresh()->nombre)->toBe('Dos');
});

it('rechaza nombre u orden repetidos (sin distinguir mayúsculas)', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    Nivel::factory()->create(['nombre' => 'Primaria', 'orden' => 1]);

    Livewire::actingAs($admin)->test(Index::class)
        ->set('nombre', 'PRIMARIA')->set('orden', '2')->call('guardar')->assertHasErrors('nombre')
        ->set('nombre', 'Otro')->set('orden', '1')->call('guardar')->assertHasErrors('orden');

    expect(Nivel::count())->toBe(1);
});

it('rechaza nombre vacío y orden inválido', function (string $nombre, string $orden, string $campo) {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Index::class)->set('nombre', $nombre)->set('orden', $orden)->call('guardar')->assertHasErrors($campo);
})->with([['', '1', 'nombre'], ['A', '', 'orden'], ['A', '0', 'orden'], ['A', 'x', 'orden']]);

it('con solo academico.ver lista pero no modifica (403)', function () {
    $consulta = usuarioConPermisos(['academico.ver']);
    $nivel = Nivel::factory()->create(['nombre' => 'Visible']);

    $this->actingAs($consulta)->get('/academico/niveles')->assertOk()->assertSee('Visible');
    Livewire::actingAs($consulta)->test(Index::class)->set('nombre', 'X')->set('orden', '9')->call('guardar')->assertForbidden();
    Livewire::actingAs($consulta)->test(Index::class)->call('editar', $nivel->id)->assertForbidden();

    expect(Nivel::count())->toBe(1);
});

it('sin academico.ver responde 403', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/academico/niveles')->assertForbidden();
});

it('pagina de 15 en 15', function () {
    $admin = usuarioConPermisos(['academico.ver']);
    Nivel::factory()->count(20)->create();

    $c = Livewire::actingAs($admin)->test(Index::class);

    expect($c->viewData('niveles')->perPage())->toBe(15)->and($c->viewData('niveles')->total())->toBe(20);
});
