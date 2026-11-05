<?php

use App\Livewire\Matricula\Alumnos\Show;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Seccion;
use Livewire\Livewire;

function gestorFicha()
{
    return usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);
}

it('muestra el historial completo de varios años con sus estados', function () {
    $alumno = Alumno::factory()->create();
    $grado = Grado::factory()->create();
    $anio2025 = AnioEscolar::factory()->create(['nombre' => '2025']);
    $anio2026 = AnioEscolar::factory()->vigente()->create(['nombre' => '2026']);
    $s2025 = Seccion::factory()->for($grado)->for($anio2025, 'anio')->create(['identificador' => 'B']);
    $s2026 = Seccion::factory()->for($grado)->for($anio2026, 'anio')->create(['identificador' => 'C']);
    Matricula::factory()->retirada()->create(['alumno_id' => $alumno->id, 'seccion_id' => $s2025->id, 'motivo' => 'Viaje']);
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $s2026->id]);

    Livewire::actingAs(gestorFicha())->test(Show::class, ['alumno' => $alumno])
        ->assertSee('2025')->assertSee('2026')->assertSee('Retirado')->assertSee('Matriculado')->assertSee('Viaje')
        ->assertViewHas('historial', fn ($h) => $h->count() === 2);
});

it('retira, corrige y vuelve a matricular desde la ficha', function () {
    ['seccion' => $seccion] = escenarioMatricula();
    $alumno = Alumno::factory()->create();
    $ficha = Livewire::actingAs(gestorFicha())->test(Show::class, ['alumno' => $alumno]);

    $ficha->set('seccionId', (string) $seccion->id)->call('matricular')->assertHasNoErrors();
    $matricula = Matricula::firstOrFail();

    $ficha->call('iniciarCambio', $matricula->id, 'retirar')->set('cambioMotivo', 'Prueba')->call('confirmarCambio')->assertHasNoErrors();
    expect($matricula->fresh()->estado->value)->toBe('retirado');

    $ficha->call('corregir', $matricula->id)->assertHasNoErrors();
    expect($matricula->fresh()->estado->value)->toBe('matriculado');
});

it('desactivar con matrícula activa muestra el error y no desactiva', function () {
    $matricula = Matricula::factory()->create();

    Livewire::actingAs(gestorFicha())->test(Show::class, ['alumno' => $matricula->alumno])
        ->call('desactivar')->assertHasErrors('general');

    expect($matricula->alumno->fresh()->activo)->toBeTrue();
});

it('elimina la ficha de un alumno sin matrículas y vuelve al listado', function () {
    $alumno = Alumno::factory()->create();

    Livewire::actingAs(gestorFicha())->test(Show::class, ['alumno' => $alumno])
        ->call('eliminar')->assertRedirect(route('matricula.alumnos.index'));

    expect(Alumno::find($alumno->id))->toBeNull();
});

it('las acciones de la ficha son solo para matricula.gestionar', function () {
    $matricula = Matricula::factory()->create();
    $director = usuarioConPermisos(['matricula.ver_todas']);

    $ficha = Livewire::actingAs($director)->test(Show::class, ['alumno' => $matricula->alumno])->assertOk();
    $ficha->assertDontSee('Retirar')->assertDontSee('Desactivar');
    Livewire::actingAs($director)->test(Show::class, ['alumno' => $matricula->alumno])->call('iniciarCambio', $matricula->id, 'retirar')->assertForbidden();
    Livewire::actingAs($director)->test(Show::class, ['alumno' => $matricula->alumno])->call('desactivar')->assertForbidden();

    expect($matricula->fresh()->estado->value)->toBe('matriculado');
});

it('la ruta de la ficha da 403 sin permiso y 403 para un alumno ajeno', function () {
    $alumno = Alumno::factory()->create();

    $this->actingAs(usuarioConPermisos([]))->get(route('matricula.alumnos.show', $alumno))->assertForbidden();
    $this->actingAs(usuarioConPermisos(['matricula.ver_propia']))->get(route('matricula.alumnos.show', $alumno))->assertForbidden();
    $this->actingAs(usuarioConPermisos(['matricula.ver_todas']))->get(route('matricula.alumnos.show', $alumno))->assertOk();
});
