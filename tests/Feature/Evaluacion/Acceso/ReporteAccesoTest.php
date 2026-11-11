<?php

use App\Livewire\Evaluacion\Reportes\Index;
use App\Models\Permiso;
use App\Models\Seccion;
use Livewire\Livewire;

it('Administrador y Director ven cualquier sección sin controles de edición', function () {
    $e = escenarioEvaluacion(1);
    esquemaEvaluacion($e);

    foreach ([$e['admin'], usuarioConPermisos(['notas.ver_todas'])] as $usuario) {
        Livewire::actingAs($usuario)->test(Index::class, ['seccion' => $e['seccion']->id])->assertOk()
            ->assertSee('Reportes de notas')->assertDontSee('Guardar');
        $this->actingAs($usuario)->get('/notas/reportes')->assertOk();
    }
});

it('el Docente solo ve secciones con asignación', function () {
    $e = escenarioEvaluacion(1);
    $ajena = Seccion::factory()->for($e['seccion']->grado)->for($e['anio'], 'anio')->create(['identificador' => 'Z']);

    Livewire::actingAs($e['docente'])->test(Index::class, ['seccion' => $e['seccion']->id])->assertOk();
    Livewire::actingAs($e['docente'])->test(Index::class, ['seccion' => $ajena->id])->assertForbidden();

    $c = Livewire::actingAs($e['docente'])->test(Index::class);
    expect($c->viewData('secciones')->pluck('id')->all())->toBe([$e['seccion']->id]);
});

it('Padre, Alumno y sin permiso reciben 403', function () {
    escenarioEvaluacion(1);

    foreach ([usuarioConPermisos(['notas.ver_propia']), usuarioConPermisos([])] as $usuario) {
        Livewire::actingAs($usuario)->test(Index::class)->assertForbidden();
        $this->actingAs($usuario)->get('/notas/reportes')->assertForbidden();
    }
});

it('quitar notas.ver_todas al Director corta su acceso', function () {
    escenarioEvaluacion(1);
    $director = usuarioConPermisos(['notas.ver_todas']);

    Livewire::actingAs($director)->test(Index::class)->assertOk();

    $director->rol->permisos()->detach(Permiso::where('identificador', 'notas.ver_todas')->value('id'));

    Livewire::actingAs($director->fresh())->test(Index::class)->assertForbidden();
});

it('el menú muestra «Reportes de notas» solo a quien corresponde', function () {
    $this->actingAs(usuarioConPermisos(['notas.ver_todas']))->get('/inicio')->assertSee('Reportes de notas');
    $this->actingAs(usuarioConPermisos(['notas.ver_propia']))->get('/inicio')->assertDontSee('Reportes de notas');
});
