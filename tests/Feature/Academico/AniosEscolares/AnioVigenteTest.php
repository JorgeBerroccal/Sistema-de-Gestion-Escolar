<?php

use App\Academico\AnioEscolarService;
use App\Academico\ReglaAcademicaIncumplida;
use App\Livewire\Academico\AniosEscolares\Index;
use App\Models\AnioEscolar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('marcar un segundo año como vigente deja uno solo, en una operación', function () {
    $servicio = app(AnioEscolarService::class);
    $a2026 = AnioEscolar::factory()->create(['nombre' => '2026']);
    $a2027 = AnioEscolar::factory()->create(['nombre' => '2027']);

    $servicio->marcarVigente($a2026);
    expect(AnioEscolar::where('vigente', 1)->pluck('id')->all())->toBe([$a2026->id]);

    $servicio->marcarVigente($a2027);

    expect(AnioEscolar::where('vigente', 1)->pluck('id')->all())->toBe([$a2027->id])
        ->and($a2026->fresh()->esVigente())->toBeFalse()
        ->and($a2027->esVigente())->toBeTrue();
});

it('marcar vigente al que ya lo es no cambia nada', function () {
    $anio = AnioEscolar::factory()->vigente()->create();

    app(AnioEscolarService::class)->marcarVigente($anio);

    expect(AnioEscolar::where('vigente', 1)->count())->toBe(1)->and($anio->fresh()->esVigente())->toBeTrue();
});

it('no marca vigente un año desactivado', function () {
    $actual = AnioEscolar::factory()->vigente()->create();
    $inactivo = AnioEscolar::factory()->inactivo()->create();

    expect(fn () => app(AnioEscolarService::class)->marcarVigente($inactivo))
        ->toThrow(ReglaAcademicaIncumplida::class, 'desactivado');

    expect($actual->fresh()->esVigente())->toBeTrue()->and($inactivo->fresh()->esVigente())->toBeFalse();
});

it('rechaza un nombre de año repetido, también con otra capitalización o espacios', function () {
    $servicio = app(AnioEscolarService::class);
    AnioEscolar::factory()->create(['nombre' => 'Año 2026']);

    $periodos = collect(range(1, 4))->map(fn ($i) => [
        'nombre' => "P$i", 'fecha_inicio' => sprintf('2026-%02d-02', 2 + $i * 2), 'fecha_fin' => sprintf('2026-%02d-20', 2 + $i * 2),
    ])->all();

    expect(fn () => $servicio->guardar(null, ['nombre' => '  año   2026 ', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-12-20', 'tipo_periodo' => 'bimestre'], $periodos))
        ->toThrow(ReglaAcademicaIncumplida::class, 'Ya existe');
});

it('normaliza el nombre al guardarlo', function () {
    $anio = AnioEscolar::factory()->create(['nombre' => '  Año    2030 ']);

    expect($anio->fresh()->nombre)->toBe('Año 2030');
});

// Concurrencia (SC-002), por separado como en ProteccionAdministracionTest: no se simulan transacciones
// concurrentes reales porque las pruebas Feature corren dentro de una transacción.
it('la base impide dos años vigentes con el índice único', function () {
    $a = AnioEscolar::factory()->vigente()->create();
    $b = AnioEscolar::factory()->create();

    expect(fn () => DB::table('anios_escolares')->where('id', $b->id)->update(['vigente' => 1]))
        ->toThrow(QueryException::class);

    expect(AnioEscolar::where('vigente', 1)->pluck('id')->all())->toBe([$a->id]);
});

it('marcarVigente bloquea con for update el año vigente y el elegido, dentro de su transacción', function () {
    $actual = AnioEscolar::factory()->vigente()->create();
    $elegido = AnioEscolar::factory()->create();
    $consultas = [];
    DB::listen(function ($q) use (&$consultas) {
        $consultas[] = ['sql' => $q->sql, 'nivel' => DB::transactionLevel()];
    });

    app(AnioEscolarService::class)->marcarVigente($elegido);

    $bloqueos = array_values(array_filter($consultas, fn ($c) => str_contains($c['sql'], 'for update')));
    expect($bloqueos)->toHaveCount(1)
        ->and($bloqueos[0]['sql'])->toContain('anios_escolares')
        ->and($bloqueos[0]['nivel'])->toBeGreaterThan(0)
        ->and($actual->fresh()->esVigente())->toBeFalse();
});

it('el listado marca el vigente con academico.gestionar y rechaza a quien solo consulta', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $consulta = usuarioConPermisos(['academico.ver']);
    $a = AnioEscolar::factory()->create();

    Livewire::actingAs($consulta)->test(Index::class)->call('marcarVigente', $a->id)->assertForbidden();
    expect($a->fresh()->esVigente())->toBeFalse();

    Livewire::actingAs($admin)->test(Index::class)->call('marcarVigente', $a->id)->assertHasNoErrors();
    expect($a->fresh()->esVigente())->toBeTrue();
});

it('el listado informa si el año está desactivado y no lo marca', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $inactivo = AnioEscolar::factory()->inactivo()->create();

    Livewire::actingAs($admin)->test(Index::class)->call('marcarVigente', $inactivo->id)->assertHasErrors('general');
});

it('el listado se pagina, muestra lista vacía sin error y exige academico.ver', function () {
    $admin = usuarioConPermisos(['academico.ver']);

    $this->actingAs($admin)->get('/academico/anios')->assertOk()->assertSee('Aún no hay años escolares');

    AnioEscolar::factory()->count(20)->create();
    $c = Livewire::actingAs($admin)->test(Index::class);
    expect($c->viewData('anios')->perPage())->toBe(15)->and($c->viewData('anios')->total())->toBe(20);

    $this->actingAs(usuarioConPermisos([]))->get('/academico/anios')->assertForbidden();
});
