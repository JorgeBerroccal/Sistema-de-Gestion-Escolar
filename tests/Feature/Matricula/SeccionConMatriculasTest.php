<?php

use App\Livewire\Academico\Secciones\Index;
use App\Models\Matricula;
use App\Models\Seccion;
use Livewire\Livewire;

it('una sección con matrículas no se elimina, solo se desactiva (FR-017)', function () {
    $seccion = Seccion::factory()->create();
    Matricula::factory()->create(['seccion_id' => $seccion->id]);
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Index::class)->call('eliminar', $seccion->id)->assertHasErrors('general');
    expect(Seccion::find($seccion->id))->not->toBeNull();

    Livewire::actingAs($admin)->test(Index::class)->call('desactivar', $seccion->id);
    expect($seccion->fresh()->activo)->toBeFalse()
        ->and($seccion->matriculas()->count())->toBe(1);
});

it('una sección sin asignaciones ni matrículas sí se elimina', function () {
    $seccion = Seccion::factory()->create();
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Index::class)->call('eliminar', $seccion->id);

    expect(Seccion::find($seccion->id))->toBeNull();
});
