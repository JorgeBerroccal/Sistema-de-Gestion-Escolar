<?php

use App\Academico\AnioEscolarService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Academico\TipoPeriodo;
use App\Livewire\Academico\AniosEscolares\Form;
use App\Models\AnioEscolar;
use Livewire\Livewire;

function bimestres(array $cambios = []): array
{
    $base = [
        ['nombre' => 'I Bimestre', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-05-08'],
        ['nombre' => 'II Bimestre', 'fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-07-24'],
        ['nombre' => 'III Bimestre', 'fecha_inicio' => '2026-08-03', 'fecha_fin' => '2026-10-09'],
        ['nombre' => 'IV Bimestre', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-12-18'],
    ];

    foreach ($cambios as $i => $cambio) {
        $base[$i] = array_merge($base[$i], $cambio);
    }

    return $base;
}

function datosAnio(array $cambios = []): array
{
    return array_merge(['nombre' => '2026', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-12-20', 'tipo_periodo' => 'bimestre'], $cambios);
}

it('guarda cuatro bimestres válidos en orden, con días libres entre ellos', function () {
    $anio = app(AnioEscolarService::class)->guardar(null, datosAnio(), bimestres());

    expect($anio->periodos)->toHaveCount(4)
        ->and($anio->periodos->pluck('orden')->all())->toBe([1, 2, 3, 4])
        ->and($anio->periodos->first()->nombre)->toBe('I Bimestre')
        ->and($anio->activo)->toBeTrue()
        ->and($anio->esVigente())->toBeFalse();
});

it('rechaza periodos inválidos explicando la regla', function (array $cambios, string $fragmento) {
    expect(fn () => app(AnioEscolarService::class)->guardar(null, datosAnio(), bimestres($cambios)))
        ->toThrow(ReglaAcademicaIncumplida::class, $fragmento);

    expect(AnioEscolar::count())->toBe(0);
})->with([
    'empieza antes del año' => [[0 => ['fecha_inicio' => '2026-02-20']], 'fuera del año'],
    'termina después del año' => [[3 => ['fecha_fin' => '2026-12-31']], 'fuera del año'],
    'empieza después de que termina' => [[1 => ['fecha_inicio' => '2026-07-30']], 'empieza después de que termina'],
    'empieza y termina el mismo día' => [[1 => ['fecha_inicio' => '2026-05-18', 'fecha_fin' => '2026-05-18']], 'empieza después de que termina'],
    'se superpone con el anterior' => [[1 => ['fecha_inicio' => '2026-05-01']], 'se superpone'],
    'el fin de uno es el inicio del siguiente' => [[1 => ['fecha_inicio' => '2026-05-08']], 'se superpone'],
    'fuera de orden cronológico' => [[0 => ['fecha_inicio' => '2026-08-04', 'fecha_fin' => '2026-08-20']], 'se superpone'],
    'sin nombre' => [[2 => ['nombre' => '']], 'necesitan nombre'],
]);

it('exige exactamente 4 bimestres o 3 trimestres', function () {
    $servicio = app(AnioEscolarService::class);

    expect(fn () => $servicio->guardar(null, datosAnio(), array_slice(bimestres(), 0, 3)))
        ->toThrow(ReglaAcademicaIncumplida::class, 'exactamente 4 periodos');

    expect(fn () => $servicio->guardar(null, datosAnio(['tipo_periodo' => 'trimestre']), bimestres()))
        ->toThrow(ReglaAcademicaIncumplida::class, 'exactamente 3 periodos');

    $anio = $servicio->guardar(null, datosAnio(['tipo_periodo' => 'trimestre']), array_slice(bimestres(), 0, 3));
    expect($anio->periodos)->toHaveCount(3)->and($anio->tipo_periodo)->toBe(TipoPeriodo::Trimestre);
});

it('un año nuevo debe definir sus periodos y la fecha de inicio debe ser anterior a la de fin', function () {
    $servicio = app(AnioEscolarService::class);

    expect(fn () => $servicio->guardar(null, datosAnio(), null))->toThrow(ReglaAcademicaIncumplida::class, 'periodos');
    expect(fn () => $servicio->guardar(null, datosAnio(['fecha_inicio' => '2026-12-20', 'fecha_fin' => '2026-03-01']), bimestres()))
        ->toThrow(ReglaAcademicaIncumplida::class, 'inicio del año');
});

it('cambiar las fechas del año dejando un periodo fuera se rechaza hasta ajustar el periodo', function () {
    $servicio = app(AnioEscolarService::class);
    $anio = $servicio->guardar(null, datosAnio(), bimestres());

    expect(fn () => $servicio->guardar($anio, datosAnio(['fecha_fin' => '2026-11-30'])))
        ->toThrow(ReglaAcademicaIncumplida::class, 'fuera del año');
    expect($anio->fresh()->fecha_fin->format('Y-m-d'))->toBe('2026-12-20');

    $ajustados = bimestres([3 => ['fecha_fin' => '2026-11-27']]);
    $servicio->guardar($anio, datosAnio(['fecha_fin' => '2026-11-30']), $ajustados);

    expect($anio->fresh()->fecha_fin->format('Y-m-d'))->toBe('2026-11-30');
});

it('editar conserva los ids de los periodos', function () {
    $servicio = app(AnioEscolarService::class);
    $anio = $servicio->guardar(null, datosAnio(), bimestres());
    $ids = $anio->periodos->pluck('id')->all();

    $servicio->guardar($anio, datosAnio(), bimestres([0 => ['nombre' => 'Primer bimestre']]));

    expect($anio->fresh()->periodos->pluck('id')->all())->toBe($ids)
        ->and($anio->fresh()->periodos->first()->nombre)->toBe('Primer bimestre');
});

it('cambiar de bimestres a trimestres regenera los periodos mientras nada dependa de ellos', function () {
    $servicio = app(AnioEscolarService::class);
    $anio = $servicio->guardar(null, datosAnio(), bimestres());

    expect(fn () => $servicio->guardar($anio, datosAnio(['tipo_periodo' => 'trimestre'])))
        ->toThrow(ReglaAcademicaIncumplida::class, 'nuevos periodos');

    $servicio->guardar($anio, datosAnio(['tipo_periodo' => 'trimestre']), array_slice(bimestres(), 0, 3));

    expect($anio->fresh()->periodos)->toHaveCount(3)->and($anio->fresh()->tipo_periodo)->toBe(TipoPeriodo::Trimestre);
});

it('no cambia el tipo de periodo si otros registros dependen de los periodos', function () {
    $servicio = new class extends AnioEscolarService
    {
        protected function periodosTienenDependientes(AnioEscolar $anio): bool
        {
            return true;
        }
    };
    $anio = app(AnioEscolarService::class)->guardar(null, datosAnio(), bimestres());

    expect(fn () => $servicio->guardar($anio, datosAnio(['tipo_periodo' => 'trimestre']), array_slice(bimestres(), 0, 3)))
        ->toThrow(ReglaAcademicaIncumplida::class, 'dependen');

    expect($anio->fresh()->tipo_periodo)->toBe(TipoPeriodo::Bimestre)->and($anio->fresh()->periodos)->toHaveCount(4);
});

it('el formulario guarda el año con sus periodos y propone los nombres', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    $c = Livewire::actingAs($admin)->test(Form::class)
        ->assertSet('periodos.0.nombre', 'I Bimestre')->assertSet('periodos.3.nombre', 'IV Bimestre')
        ->set('nombre', '2026')->set('fecha_inicio', '2026-03-01')->set('fecha_fin', '2026-12-20');

    foreach (bimestres() as $i => $p) {
        $c->set("periodos.$i.fecha_inicio", $p['fecha_inicio'])->set("periodos.$i.fecha_fin", $p['fecha_fin']);
    }

    $c->call('guardar')->assertHasNoErrors()->assertRedirect(route('academico.anios.index'));

    expect(AnioEscolar::first()->periodos)->toHaveCount(4);
});

it('el formulario muestra la regla incumplida y no guarda', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    $c = Livewire::actingAs($admin)->test(Form::class)
        ->set('nombre', '2026')->set('fecha_inicio', '2026-03-01')->set('fecha_fin', '2026-12-20');

    foreach (bimestres([1 => ['fecha_inicio' => '2026-05-01']]) as $i => $p) {
        $c->set("periodos.$i.fecha_inicio", $p['fecha_inicio'])->set("periodos.$i.fecha_fin", $p['fecha_fin']);
    }

    $c->call('guardar')->assertHasErrors('general');

    expect(AnioEscolar::count())->toBe(0);
});

it('cambiar el tipo en el formulario ajusta la cantidad de periodos', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    Livewire::actingAs($admin)->test(Form::class)->set('tipo_periodo', 'trimestre')
        ->assertCount('periodos', 3)->assertSet('periodos.2.nombre', 'III Trimestre');
});

it('el formulario exige academico.gestionar', function () {
    $consulta = usuarioConPermisos(['academico.ver']);

    $this->actingAs($consulta)->get('/academico/anios/crear')->assertForbidden();
    $this->actingAs(usuarioConPermisos(['academico.gestionar']))->get('/academico/anios/crear')->assertOk();
});
