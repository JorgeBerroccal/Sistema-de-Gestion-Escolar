<?php

use App\Models\CierrePeriodo;
use App\Models\Curso;
use App\Models\Nota;

function postNotas(array $e, array $notas, $usuario, ?array $extra = null)
{
    return test()->actingAs($usuario)->post(
        route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]),
        ['periodo' => $e['periodo']->id, 'notas' => $notas] + ($extra ?? []),
    );
}

it('el guardado es un POST clásico dentro del grupo web (con CSRF) y el formulario lleva el token', function () {
    $e = escenarioEvaluacion(1);
    esquemaEvaluacion($e);

    $ruta = Route::getRoutes()->getByName('notas.registro.guardar');

    expect($ruta->methods())->toContain('POST')
        ->and($ruta->gatherMiddleware())->toContain('web')->toContain('auth');

    $html = test()->actingAs($e['docente'])->get(route('notas.registro.show', ['seccion' => $e['seccion'], 'curso' => $e['curso']]))->assertOk()->getContent();

    expect($html)->toContain('name="_token"')
        ->and($html)->toContain('name="periodo"')
        ->and($html)->not->toContain('wire:model="notas');
});

it('éxito: 302 de vuelta con aviso y las notas quedan guardadas', function () {
    $e = escenarioEvaluacion(2);
    [$a, $b] = esquemaEvaluacion($e)->all();
    [$ana, $beto] = $e['alumnos']->all();

    postNotas($e, [$ana->id => [$a->id => '15', $b->id => '12'], $beto->id => [$a->id => '', $b->id => '9']], $e['docente'])
        ->assertRedirect(route('notas.registro.show', ['seccion' => $e['seccion'], 'curso' => $e['curso'], 'periodo' => $e['periodo']->id]))
        ->assertSessionHas('estado')->assertSessionHasNoErrors();

    expect(Nota::count())->toBe(3);
});

it('celda inválida: 302 con errores por fila y columna y nada guardado', function () {
    $e = escenarioEvaluacion(2);
    [$a, $b] = esquemaEvaluacion($e)->all();
    [$ana, $beto] = $e['alumnos']->all();
    $ana->update(['apellidos' => 'Aaa']);
    $beto->update(['apellidos' => 'Bbb']);

    postNotas($e, [$ana->id => [$a->id => '15', $b->id => '12'], $beto->id => [$a->id => 'x', $b->id => '30']], $e['docente'])
        ->assertRedirect()->assertSessionHasErrors('notas');

    expect(Nota::count())->toBe(0)
        ->and(session('errors')->get('notas'))->toHaveCount(2)
        ->and(implode(' ', session('errors')->get('notas')))->toContain('Fila 2, columna 1')->toContain('Fila 2, columna 2');
});

it('403 sin permiso, con un par ajeno y para Padre, Alumno y Director; no se guarda nada', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    $celdas = [$e['alumnos']->first()->id => [$a->id => '10']];

    foreach ([['notas.ver_propia'], ['notas.ver_todas'], []] as $permisos) {
        postNotas($e, $celdas, usuarioConPermisos($permisos))->assertForbidden();
    }

    $ajeno = Curso::factory()->create();
    test()->actingAs($e['docente'])->post(
        route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $ajeno]),
        ['periodo' => $e['periodo']->id, 'notas' => $celdas],
    )->assertForbidden();

    expect(Nota::count())->toBe(0);
});

it('sin sesión redirige al login', function () {
    $e = escenarioEvaluacion(1);

    test()->post(route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]), ['periodo' => $e['periodo']->id])
        ->assertRedirect(route('login'));
});

it('con el periodo cerrado el docente recibe el error y no se guarda; el Administrador sí guarda', function () {
    $e = escenarioEvaluacion(1);
    [$a] = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]])->all();
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);
    $celdas = [$e['alumnos']->first()->id => [$a->id => '18']];

    postNotas($e, $celdas, $e['docente'])->assertRedirect()->assertSessionHasErrors('notas');
    expect(Nota::count())->toBe(0);

    postNotas($e, $celdas, $e['admin'])->assertRedirect()->assertSessionHasNoErrors();
    expect(Nota::count())->toBe(1);
});

it('valida el periodo y el formato de la petición', function () {
    $e = escenarioEvaluacion(1);

    test()->actingAs($e['docente'])->post(
        route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]),
        ['periodo' => 999999, 'notas' => []],
    )->assertSessionHasErrors('periodo');

    test()->actingAs($e['docente'])->post(
        route('notas.registro.guardar', ['seccion' => $e['seccion'], 'curso' => $e['curso']]),
        ['periodo' => $e['periodo']->id, 'notas' => 'no-es-arreglo'],
    )->assertSessionHasErrors('notas');
});
