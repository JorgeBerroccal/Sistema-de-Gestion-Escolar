<?php

use App\Evaluacion\EvaluacionService;
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

it('con escala literal el orden de mérito muestra el valor numérico con el que se ordena', function () {
    $e = escenarioEvaluacion(2, 'literal');
    $criterios = esquemaEvaluacion($e);
    [$a, $b] = $e['alumnos']->all();
    $a->update(['apellidos' => 'Zeta', 'nombres' => 'Ana']);
    $b->update(['apellidos' => 'Alfa', 'nombres' => 'Beto']);
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [
        $a->id => [$criterios[0]->id => 'AD', $criterios[1]->id => 'AD'],  // 4,00 → AD
        $b->id => [$criterios[0]->id => 'AD', $criterios[1]->id => 'A'],   // 3,60 → AD
    ], $e['admin']);

    Livewire::actingAs($e['admin'])->test(Index::class, ['seccion' => $e['seccion']->id])
        ->assertSee('Valor de orden')->assertSeeInOrder(['Zeta', '4,00', 'Alfa', '3,60']);
});

it('con escala numérica no agrega la columna de valor de orden', function () {
    $e = escenarioEvaluacion(1);
    $criterios = esquemaEvaluacion($e);
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [
        $e['alumnos'][0]->id => [$criterios[0]->id => '15', $criterios[1]->id => '10'],
    ], $e['admin']);

    Livewire::actingAs($e['admin'])->test(Index::class, ['seccion' => $e['seccion']->id])
        ->assertDontSee('Valor de orden')->assertSee('13,00');
});
