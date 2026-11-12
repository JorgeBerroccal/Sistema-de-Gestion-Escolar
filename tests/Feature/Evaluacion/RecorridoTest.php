<?php

use App\Livewire\Evaluacion\Cierres\Index as Cierres;
use App\Livewire\Evaluacion\Propia\Index as Propia;
use App\Livewire\Evaluacion\Registro\Show;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Nota;
use Livewire\Livewire;

it('recorrido de la prueba de humo: el docente define criterios y guarda, el Administrador cierra, el Padre consulta y descarga la boleta, el Administrador reabre', function () {
    $e = escenarioEvaluacion(2);
    [$ana, $beto] = $e['alumnos']->all();
    $padre = usuarioConPermisos(['notas.ver_propia', 'boletas.ver_propia']);
    $ana->update(['apellidos' => 'Hijo', 'nombres' => 'Propio']);
    $beto->update(['apellidos' => 'Ajeno', 'nombres' => 'Reservado']);
    $ana->apoderados()->attach($padre->id, ['parentesco' => 'padre']);

    // 1–2. El Docente define dos criterios (60 % y 40 %) y guarda las notas de la sección con el formulario clásico.
    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])
        ->set('criterios', [['id' => null, 'nombre' => 'Práctica', 'peso' => '70'], ['id' => null, 'nombre' => 'Examen', 'peso' => '40']])
        ->call('guardarCriterios')->assertHasErrors('criterios');
    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])
        ->set('criterios', [['id' => null, 'nombre' => 'Práctica', 'peso' => '60'], ['id' => null, 'nombre' => 'Examen', 'peso' => '40']])
        ->call('guardarCriterios')->assertHasNoErrors();
    [$practica, $examen] = CriterioEvaluacion::orderBy('orden')->get()->all();

    $this->actingAs($e['docente'])->post(route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]), [
        'periodo' => $e['periodo']->id,
        'notas' => [$ana->id => [$practica->id => '15', $examen->id => '10'], $beto->id => [$practica->id => '8', $examen->id => '12']],
    ])->assertSessionHasNoErrors();
    expect(Nota::count())->toBe(4);

    // Antes del cierre la familia no ve nada.
    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Aún no hay periodos publicados');
    $this->actingAs($padre)->get(route('boletas.descargar', [$ana->id, $e['periodo']->id]))->assertForbidden();

    // 3. El Administrador cierra el periodo; el Docente queda en solo lectura.
    Livewire::actingAs($e['admin'])->test(Cierres::class)->call('cerrar', $e['seccion']->id, $e['periodo']->id)->assertSee('Cerrado');
    Livewire::actingAs($e['docente'])->test(Show::class, ['seccion' => $e['seccion'], 'curso' => $e['curso']])->assertSee('Periodo cerrado')->assertDontSee('Guardar notas');
    $this->actingAs($e['docente'])->post(route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]), [
        'periodo' => $e['periodo']->id, 'notas' => [$ana->id => [$practica->id => '20']],
    ])->assertSessionHasErrors('notas');
    expect(Nota::where('alumno_id', $ana->id)->where('criterio_id', $practica->id)->value('valor'))->toBe('15');

    // 5–6. El Padre ve a su hijo (y a ningún otro) con promedio 13.00 y descarga la boleta.
    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Hijo, Propio')->assertDontSee('Reservado')->assertSee('13,00');
    $r = $this->actingAs($padre)->get(route('boletas.descargar', [$ana->id, $e['periodo']->id]));
    $r->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($r->getContent())->toStartWith('%PDF');
    $this->actingAs($padre)->get(route('boletas.descargar', [$beto->id, $e['periodo']->id]))->assertForbidden();

    // 7. El Administrador reabre; la familia deja de ver el periodo y el Docente vuelve a editar.
    Livewire::actingAs($e['admin'])->test(Cierres::class)->call('reabrir', $e['seccion']->id, $e['periodo']->id)->assertSee('Abierto');
    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Aún no hay periodos publicados');
    $this->actingAs($padre)->get(route('boletas.descargar', [$ana->id, $e['periodo']->id]))->assertForbidden();
    $this->actingAs($e['docente'])->post(route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]), [
        'periodo' => $e['periodo']->id, 'notas' => [$ana->id => [$practica->id => '20']],
    ])->assertSessionHasNoErrors();
    expect(Nota::where('alumno_id', $ana->id)->where('criterio_id', $practica->id)->value('valor'))->toBe('20');
});

it('SC-002: desde el inicio, el Padre llega a la boleta en tres clics', function () {
    $e = escenarioEvaluacion(1);
    esquemaEvaluacion($e);
    $ana = $e['alumnos']->first();
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);
    $padre = usuarioConPermisos(['notas.ver_propia', 'boletas.ver_propia']);
    $ana->apoderados()->attach($padre->id, ['parentesco' => 'madre']);

    // Clic 1: «Mis notas» en el menú del inicio.
    $this->actingAs($padre)->get('/inicio')->assertSee('href="'.route('notas.propia.index').'"', false);
    // Clic 2: «Mis notas» ya muestra al hijo y el periodo publicado; clic 3: «Descargar boleta».
    $this->actingAs($padre)->get(route('notas.propia.index'))->assertOk()
        ->assertSee('href="'.route('boletas.descargar', [$ana->id, $e['periodo']->id]).'"', false);
    $this->actingAs($padre)->get(route('boletas.descargar', [$ana->id, $e['periodo']->id]))->assertOk();
});
