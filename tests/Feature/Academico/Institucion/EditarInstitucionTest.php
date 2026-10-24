<?php

use App\Livewire\Academico\Institucion\Edit;
use App\Models\EscalaCalificacion;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Nivel;
use Database\Seeders\EscalasCalificacionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function datosValidosInstitucion(array $cambios = []): array
{
    $nivel = Nivel::factory()->create();

    return array_merge([
        'nombre' => 'Colegio de prueba',
        'codigo_modular' => '0123456',
        'ugel_dre' => 'UGEL 01',
        'niveles' => [$nivel->id],
    ], $cambios);
}

function guardarInstitucion($usuario, array $datos)
{
    $c = Livewire::actingAs($usuario)->test(Edit::class);
    foreach ($datos as $campo => $valor) {
        $c->set($campo, $valor);
    }

    return $c->call('guardar');
}

it('guarda la ficha con sus niveles y se muestra', function () {
    $this->seed(EscalasCalificacionSeeder::class);
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $escala = EscalaCalificacion::where('codigo', 'vigesimal')->first();

    guardarInstitucion($admin, datosValidosInstitucion(['escala_calificacion_id' => $escala->id]))->assertHasNoErrors();

    $ficha = Institucion::find(1);
    expect($ficha->nombre)->toBe('Colegio de prueba')
        ->and($ficha->codigo_modular)->toBe('0123456')
        ->and($ficha->niveles)->toHaveCount(1)
        ->and($ficha->escala_calificacion_id)->toBe($escala->id);

    $this->actingAs($admin)->get('/academico/institucion')->assertOk()->assertSee('Colegio de prueba')->assertSee('Vigesimal');
});

it('guardar dos veces actualiza la misma ficha, no crea otra', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    guardarInstitucion($admin, datosValidosInstitucion());
    guardarInstitucion($admin, datosValidosInstitucion(['nombre' => 'Otro nombre']));

    expect(Institucion::count())->toBe(1)->and(Institucion::first()->nombre)->toBe('Otro nombre');
});

it('rechaza la ficha incompleta indicando el campo', function (string $campo, mixed $valor, ?string $claveError = null) {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);

    guardarInstitucion($admin, datosValidosInstitucion([$campo => $valor]))->assertHasErrors($claveError ?? $campo);

    expect(Institucion::count())->toBe(0);
})->with([
    'sin nombre' => ['nombre', ''],
    'sin código modular' => ['codigo_modular', ''],
    'sin UGEL o dirección regional' => ['ugel_dre', ''],
    'sin nivel atendido' => ['niveles', []],
    'con nivel inexistente' => ['niveles', [999999], 'niveles.0'],
    'con escala inexistente' => ['escala_calificacion_id', 999999],
]);

it('no acepta un nivel desactivado', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $inactivo = Nivel::factory()->inactivo()->create();

    guardarInstitucion($admin, datosValidosInstitucion(['niveles' => [$inactivo->id]]))->assertHasErrors('niveles.0');
});

it('con academico.ver se consulta pero guardar responde 403', function () {
    $consulta = usuarioConPermisos(['academico.ver']);

    $this->actingAs($consulta)->get('/academico/institucion')->assertOk();
    guardarInstitucion($consulta, datosValidosInstitucion())->assertForbidden();

    expect(Institucion::count())->toBe(0);
});

it('sin academico.ver la ruta responde 403', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/academico/institucion')->assertForbidden();
});

it('la base impide una segunda ficha', function () {
    Institucion::factory()->create();

    expect(fn () => DB::table('instituciones')->insert(['id' => 2, 'nombre' => 'X', 'codigo_modular' => '1', 'ugel_dre' => 'Y']))
        ->toThrow(QueryException::class);
});

it('no deja quitar un nivel atendido que tiene grados activos', function () {
    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar']);
    $conGrados = Nivel::factory()->create();
    $libre = Nivel::factory()->create();
    Grado::factory()->for($conGrados)->create();
    $ficha = Institucion::factory()->create();
    $ficha->niveles()->sync([$conGrados->id, $libre->id]);

    $c = Livewire::actingAs($admin)->test(Edit::class)
        ->set('niveles', [(string) $libre->id])->call('guardar')->assertHasErrors('niveles');

    expect($ficha->niveles()->count())->toBe(2);

    $c->set('niveles', [(string) $conGrados->id])->call('guardar')->assertHasNoErrors();
    expect($ficha->niveles()->pluck('niveles.id')->all())->toBe([$conGrados->id]);
});
