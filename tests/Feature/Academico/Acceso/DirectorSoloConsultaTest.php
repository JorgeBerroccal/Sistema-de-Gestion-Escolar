<?php

use App\Academico\AsignacionService;
use App\Livewire\Academico\AniosEscolares\Index as AniosIndex;
use App\Livewire\Academico\Asignaciones\Form as AsignacionForm;
use App\Livewire\Academico\Asignaciones\Index as AsignacionesIndex;
use App\Livewire\Academico\Cursos\Form as CursoForm;
use App\Livewire\Academico\Grados\Index as GradosIndex;
use App\Livewire\Academico\Institucion\Edit as InstitucionEdit;
use App\Livewire\Academico\Niveles\Index as NivelesIndex;
use App\Livewire\Academico\Secciones\Index as SeccionesIndex;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

it('el Director ve todo el módulo', function () {
    $director = usuarioConRolDeSeeder('Director');

    foreach (['institucion', 'niveles', 'anios', 'grados', 'secciones', 'cursos', 'asignaciones'] as $ruta) {
        $this->actingAs($director)->get("/academico/{$ruta}")->assertOk();
    }
});

it('cada acción de modificación del módulo responde 403 al Director y no cambia datos', function () {
    $director = usuarioConRolDeSeeder('Director');
    ['anio' => $anio, 'seccion' => $seccion, 'cursos' => $cursos, 'docente' => $docente] = escenarioAsignacion(1);
    $asignacion = app(AsignacionService::class)->asignar($seccion, $cursos[0], $docente);
    $nivel = Nivel::factory()->create(['nombre' => 'Original']);
    $grado = Grado::factory()->create(['nombre' => 'Original']);
    $otroAnio = AnioEscolar::factory()->create();

    Livewire::actingAs($director)->test(InstitucionEdit::class)->set('nombre', 'X')->call('guardar')->assertForbidden();
    Livewire::actingAs($director)->test(NivelesIndex::class)->set('nombre', 'X')->set('orden', '99')->call('guardar')->assertForbidden();
    Livewire::actingAs($director)->test(NivelesIndex::class)->call('editar', $nivel->id)->assertForbidden();
    Livewire::actingAs($director)->test(AniosIndex::class)->call('marcarVigente', $otroAnio->id)->assertForbidden();
    Livewire::actingAs($director)->test(GradosIndex::class)->set('nombre', 'X')->call('guardar')->assertForbidden();
    Livewire::actingAs($director)->test(GradosIndex::class)->call('editar', $grado->id)->assertForbidden();
    Livewire::actingAs($director)->test(SeccionesIndex::class)->set('identificador', 'Z')->call('guardar')->assertForbidden();
    Livewire::actingAs($director)->test(CursoForm::class)->assertForbidden();
    Livewire::actingAs($director)->test(AsignacionForm::class)->assertForbidden();
    Livewire::actingAs($director)->test(AsignacionesIndex::class)->call('quitar', $asignacion->id)->assertForbidden();

    expect(Nivel::where('nombre', 'X')->exists())->toBeFalse()
        ->and($nivel->fresh()->nombre)->toBe('Original')
        ->and(AnioEscolar::find($anio->id)->esVigente())->toBeTrue()
        ->and($otroAnio->fresh()->esVigente())->toBeFalse()
        ->and(AsignacionDocente::count())->toBe(1)
        ->and(Seccion::where('identificador', 'Z')->exists())->toBeFalse()
        ->and(Grado::where('nombre', 'X')->exists())->toBeFalse();
});
