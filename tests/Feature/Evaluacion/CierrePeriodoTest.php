<?php

use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Livewire\Evaluacion\Propia\Index as Propia;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Livewire\Livewire;

it('cerrar crea una fila única con quién y cuándo, y cerrar dos veces no duplica', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);

    $primero = $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);
    $segundo = $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    expect(CierrePeriodo::count())->toBe(1)
        ->and($primero->id)->toBe($segundo->id)
        ->and($primero->cerrado_por)->toBe($e['admin']->id)
        ->and($primero->cerrado_en)->not->toBeNull()
        ->and($servicio->periodoCerrado($e['seccion'], $e['periodo']))->toBeTrue();
});

it('reabrir elimina la fila y es seguro repetirlo', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    $servicio->reabrirPeriodo($e['seccion'], $e['periodo'], $e['admin']);
    $servicio->reabrirPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    expect(CierrePeriodo::count())->toBe(0)->and($servicio->periodoCerrado($e['seccion'], $e['periodo']))->toBeFalse();
});

it('tras cerrar el docente no guarda y la familia ve; tras reabrir, lo contrario', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $criterios = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]]);
    $ana = $e['alumnos']->first();
    $celdas = [$ana->id => [$criterios[0]->id => '16']];
    $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $e['docente']);
    $padre = usuarioConPermisos(['notas.ver_propia']);
    $ana->apoderados()->attach($padre->id, ['parentesco' => 'madre']);

    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Aún no hay periodos publicados');

    $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    expect(fn () => $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], $celdas, $e['docente']))->toThrow(EvaluacionIncumplida::class);
    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Único')->assertSee('16');

    $servicio->reabrirPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$ana->id => [$criterios[0]->id => '17']], $e['docente']);
    Livewire::actingAs($padre)->test(Propia::class)->assertSee('Aún no hay periodos publicados');
});

it('el Administrador sigue pudiendo corregir notas con el periodo cerrado', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $criterios = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]]);
    $ana = $e['alumnos']->first();
    $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$ana->id => [$criterios[0]->id => '19']], $e['admin']);

    expect(Nota::first()->valor)->toBe('19');
});

it('el cierre es por sección: cerrar una no cierra la otra', function () {
    $e = escenarioEvaluacion(1);
    $otra = Seccion::factory()->for($e['seccion']->grado)->for($e['anio'], 'anio')->create(['identificador' => 'B']);
    $servicio = app(EvaluacionService::class);

    $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $e['admin']);

    expect($servicio->periodoCerrado($otra, $e['periodo']))->toBeFalse();
});

it('solo se cierra el año vigente y un periodo del año de la sección', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $ajeno = PeriodoEvaluacion::factory()->create();

    expect(fn () => $servicio->cerrarPeriodo($e['seccion'], $ajeno, $e['admin']))->toThrow(EvaluacionIncumplida::class);

    AnioEscolar::whereKey($e['anio']->id)->update(['vigente' => null]);

    expect(fn () => $servicio->cerrarPeriodo($e['seccion']->fresh(), $e['periodo'], $e['admin']))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $servicio->reabrirPeriodo($e['seccion']->fresh(), $e['periodo'], $e['admin']))->toThrow(EvaluacionIncumplida::class);
});
