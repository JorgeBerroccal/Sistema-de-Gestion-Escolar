<?php

use App\Models\Rol;
use App\Models\Usuario;

it('muestra en el encabezado las iniciales del usuario', function () {
    $u = Usuario::factory()->for(Rol::factory())->create(['nombres' => 'Jorge Luis', 'apellidos' => 'Berroccal Paz']);

    $this->actingAs($u)->get(route('inicio'))->assertOk()->assertSeeHtml('data-avatar-usuario')->assertSee('JB');
});

it('calcula iniciales con tildes, minúsculas y nombres vacíos', function () {
    expect(Usuario::make(['nombres' => 'ñandú', 'apellidos' => 'álvarez'])->iniciales)->toBe('ÑÁ')
        ->and(Usuario::make(['nombres' => '', 'apellidos' => ''])->iniciales)->toBe('?');
});

it('el color es estable para el mismo nombre y siempre uno de los de texto blanco con buen contraste', function () {
    $u = Usuario::factory()->for(Rol::factory())->create(['nombres' => 'Ana', 'apellidos' => 'Paz']);
    $html = fn () => $this->actingAs($u)->get(route('inicio'))->getContent();

    expect($html())->toMatch('/class="[^"]*\bbg-(indigo|emerald|rose|violet|teal)-700\b[^"]*"[^>]*data-avatar-usuario|class="[^"]*\bbg-(sky|amber|fuchsia|cyan)-800\b[^"]*"[^>]*data-avatar-usuario/')
        ->and($html())->toBe($html());
});
