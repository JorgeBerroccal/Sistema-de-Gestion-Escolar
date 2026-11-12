<?php

use App\Livewire\Evaluacion\Cierres\Index as Cierres;
use App\Livewire\Evaluacion\Registro\Index as Registro;
use App\Livewire\Evaluacion\Reportes\Index as Reportes;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

/** Dos «1.° grado A» de niveles distintos, ambos con curso en su plan de estudios. */
function seccionesHomonimas(): array
{
    $e = escenarioEvaluacion(1);
    $primaria = Nivel::factory()->create(['nombre' => 'Primaria', 'orden' => 1]);
    $secundaria = Nivel::factory()->create(['nombre' => 'Secundaria', 'orden' => 2]);
    $gradoP = Grado::factory()->create(['nivel_id' => $primaria->id, 'nombre' => '1.° grado']);
    $gradoS = Grado::factory()->create(['nivel_id' => $secundaria->id, 'nombre' => '1.° grado']);
    $e['seccion']->update(['grado_id' => $gradoS->id, 'identificador' => 'A']);
    $otra = Seccion::factory()->for($gradoP)->for($e['anio'], 'anio')->create(['identificador' => 'A']);
    $gradoP->cursos()->attach(Curso::factory()->create(['nivel_id' => $primaria->id]));
    $gradoS->cursos()->attach($e['curso']);

    return $e + ['otra' => $otra];
}

it('Cierres de periodo muestra el nivel junto al grado y ordena por nivel', function () {
    $e = seccionesHomonimas();

    Livewire::actingAs($e['admin'])->test(Cierres::class)
        ->assertSeeInOrder(['Primaria · 1.° grado A', 'Secundaria · 1.° grado A']);
});

it('Registro de notas distingue los dos 1.° grado A por su nivel', function () {
    $e = seccionesHomonimas();

    Livewire::actingAs($e['admin'])->test(Registro::class)
        ->assertSee('Primaria · 1.° grado A')->assertSee('Secundaria · 1.° grado A');
});

it('Reportes de notas ofrece las secciones con su nivel', function () {
    $e = seccionesHomonimas();

    Livewire::actingAs($e['admin'])->test(Reportes::class)
        ->assertSee('Primaria · 1.° grado A')->assertSee('Secundaria · 1.° grado A');
});

it('el editor de notas muestra el nivel', function () {
    $e = seccionesHomonimas();

    $this->actingAs($e['admin'])->get(route('notas.registro.show', ['seccion' => $e['seccion']->id, 'curso' => $e['curso']->id]))
        ->assertOk()->assertSee('Secundaria · 1.° grado A');
});
