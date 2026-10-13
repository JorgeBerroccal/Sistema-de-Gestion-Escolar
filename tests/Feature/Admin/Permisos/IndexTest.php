<?php

use App\Livewire\Admin\Permisos\Index;
use App\Models\Permiso;
use Livewire\Livewire;

it('lista identificador, nombre y descripción con roles.ver', function () {
    Permiso::factory()->create(['identificador' => 'demo.listar', 'nombre' => 'Listar demo', 'descripcion' => 'Descripción demo']);

    $this->actingAs(usuarioConPermisos(['roles.ver']))->get('/admin/permisos')
        ->assertOk()->assertSee('demo.listar')->assertSee('Listar demo')->assertSee('Descripción demo');
});

it('deniega la lista sin roles.ver', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/admin/permisos')->assertForbidden();
});

it('crea un permiso con permisos.gestionar', function () {
    $actor = usuarioConPermisos(['roles.ver', 'permisos.gestionar']);

    Livewire::actingAs($actor)->test(Index::class)
        ->set('identificador', 'notas.ver')->set('nombre', 'Ver notas')->set('descripcion', 'Consulta')
        ->call('crear')->assertHasNoErrors();

    expect(Permiso::where('identificador', 'notas.ver')->first())->nombre->toBe('Ver notas');
});

it('valida la creación', function (array $datos, string $campo) {
    $actor = usuarioConPermisos(['roles.ver', 'permisos.gestionar']);
    Permiso::factory()->create(['identificador' => 'existe.ya']);
    $datos += ['identificador' => 'notas.ver', 'nombre' => 'Ver notas', 'descripcion' => ''];

    $c = Livewire::actingAs($actor)->test(Index::class);
    foreach ($datos as $k => $v) {
        $c->set($k, $v);
    }
    $c->call('crear')->assertHasErrors($campo);
})->with([
    'identificador duplicado' => [['identificador' => 'existe.ya'], 'identificador'],
    'sin punto' => [['identificador' => 'notas'], 'identificador'],
    'mayúsculas' => [['identificador' => 'Notas.ver'], 'identificador'],
    'nombre vacío' => [['nombre' => ''], 'nombre'],
    'nombre > 100' => [['nombre' => str_repeat('a', 101)], 'nombre'],
    'descripción > 255' => [['descripcion' => str_repeat('a', 256)], 'descripcion'],
]);

it('actualiza nombre y descripción pero nunca el identificador', function () {
    $actor = usuarioConPermisos(['roles.ver', 'permisos.gestionar']);
    $permiso = Permiso::factory()->create(['identificador' => 'fijo.uno']);

    Livewire::actingAs($actor)->test(Index::class)
        ->set('edicion.'.$permiso->id.'.nombre', 'Nuevo nombre')
        ->set('edicion.'.$permiso->id.'.descripcion', 'Nueva desc')
        ->set('edicion.'.$permiso->id.'.identificador', 'otro.cambio')
        ->call('actualizar', $permiso->id)->assertHasNoErrors();

    $permiso->refresh();
    expect($permiso->nombre)->toBe('Nuevo nombre')
        ->and($permiso->descripcion)->toBe('Nueva desc')
        ->and($permiso->identificador)->toBe('fijo.uno');
});

it('deniega crear y actualizar con solo roles.ver', function () {
    $actor = usuarioConPermisos(['roles.ver']);
    $permiso = Permiso::factory()->create(['nombre' => 'Original']);

    Livewire::actingAs($actor)->test(Index::class)
        ->set('identificador', 'notas.ver')->set('nombre', 'Ver notas')->call('crear')->assertForbidden();
    Livewire::actingAs($actor)->test(Index::class)
        ->set('edicion.'.$permiso->id.'.nombre', 'Hack')->call('actualizar', $permiso->id)->assertForbidden();

    expect(Permiso::where('identificador', 'notas.ver')->exists())->toBeFalse()
        ->and($permiso->fresh()->nombre)->toBe('Original');
});
