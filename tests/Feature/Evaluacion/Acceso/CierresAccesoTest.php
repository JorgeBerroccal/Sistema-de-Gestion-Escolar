<?php

use App\Evaluacion\EvaluacionService;
use App\Livewire\Evaluacion\Cierres\Index;
use App\Models\CierrePeriodo;
use App\Models\Permiso;
use App\Models\Seccion;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('solo quien tiene notas.cerrar_periodo abre la pantalla, cierra y reabre desde ella', function () {
    $e = escenarioEvaluacion(1);

    Livewire::actingAs($e['admin'])->test(Index::class)->assertOk()->assertSee('Abierto')
        ->call('cerrar', $e['seccion']->id, $e['periodo']->id)->assertSee('Cerrado')->assertSee('Periodo cerrado y publicado.');

    expect(CierrePeriodo::count())->toBe(1);

    Livewire::actingAs($e['admin'])->test(Index::class)
        ->call('reabrir', $e['seccion']->id, $e['periodo']->id)->assertSee('Abierto');

    expect(CierrePeriodo::count())->toBe(0);
});

it('Docente, Padre, Alumno, Director y sin permiso reciben 403', function () {
    $e = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);

    foreach ([$e['docente'], usuarioConPermisos(['notas.ver_propia']), usuarioConPermisos(['notas.ver_todas']), usuarioConPermisos(['notas.gestionar']), usuarioConPermisos([])] as $usuario) {
        Livewire::actingAs($usuario)->test(Index::class)->assertForbidden();
        $this->actingAs($usuario)->get('/notas/cierres')->assertForbidden();
        expect(fn () => $servicio->cerrarPeriodo($e['seccion'], $e['periodo'], $usuario))->toThrow(AccessDeniedHttpException::class)
            ->and(fn () => $servicio->reabrirPeriodo($e['seccion'], $e['periodo'], $usuario))->toThrow(AccessDeniedHttpException::class);
    }

    expect(CierrePeriodo::count())->toBe(0);
});

it('el Docente no puede reabrir un periodo cerrado', function () {
    $e = escenarioEvaluacion(1);
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    expect(fn () => app(EvaluacionService::class)->reabrirPeriodo($e['seccion'], $e['periodo'], $e['docente']))->toThrow(AccessDeniedHttpException::class)
        ->and(CierrePeriodo::count())->toBe(1);
});

it('un rol creado en la prueba con y sin el permiso', function () {
    $e = escenarioEvaluacion(1);
    $usuario = usuarioConPermisos(['notas.cerrar_periodo']);

    Livewire::actingAs($usuario)->test(Index::class)->assertOk();
    $this->actingAs($usuario)->get('/notas/cierres')->assertOk()->assertSee('Cierres de periodo');

    $usuario->rol->permisos()->detach(Permiso::where('identificador', 'notas.cerrar_periodo')->value('id'));

    Livewire::actingAs($usuario->fresh())->test(Index::class)->assertForbidden();
});

it('lista las secciones del año vigente paginadas con sus periodos y el menú «Cierres de periodo»', function () {
    $e = escenarioEvaluacion(1);
    Seccion::factory()->count(16)->for($e['seccion']->grado)->for($e['anio'], 'anio')->create();

    $c = Livewire::actingAs($e['admin'])->test(Index::class);

    expect($c->viewData('secciones')->total())->toBe(17)->and($c->viewData('secciones')->count())->toBe(15)
        ->and($c->viewData('periodos'))->toHaveCount(1);

    $this->actingAs(usuarioConPermisos(['notas.cerrar_periodo']))->get('/inicio')->assertSee('Cierres de periodo');
});
