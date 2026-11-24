<?php

use App\Models\Nota;

require_once __DIR__.'/Ayudas.php';

it('el Docente registra notas desde el navegador y ve la confirmación', function () {
    $e = escenarioEvaluacion(2);
    $criterios = esquemaEvaluacion($e);
    $alumno = $e['alumnos']->first();

    ingresar($e['docente']);

    visit(route('notas.registro.show', ['seccion' => $e['seccion']->id, 'curso' => $e['curso']->id]))
        ->assertSee($alumno->nombre_completo)
        ->fill('[name="notas['.$alumno->id.']['.$criterios[0]->id.']"]', '15')
        ->fill('[name="notas['.$alumno->id.']['.$criterios[1]->id.']"]', '10')
        ->press('Guardar notas')
        ->assertSee('Notas guardadas');

    // 15 × 60 % + 10 × 40 % = 13.
    expect(Nota::where('alumno_id', $alumno->id)->count())->toBe(2);
});
