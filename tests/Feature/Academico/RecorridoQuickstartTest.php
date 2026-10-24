<?php

// Recorrido automatizado del quickstart §2 (historias 1–5) con los componentes reales y los roles del seeder.
// Mide la configuración de una institución nueva de extremo a extremo (SC-001); el cronometraje humano
// va aparte, en evidencias/recorrido-local.md.

use App\Livewire\Academico\AniosEscolares\Form as AnioForm;
use App\Livewire\Academico\AniosEscolares\Index as AniosIndex;
use App\Livewire\Academico\Asignaciones\Form as AsignacionForm;
use App\Livewire\Academico\Asignaciones\Index as AsignacionesIndex;
use App\Livewire\Academico\Cursos\Form as CursoForm;
use App\Livewire\Academico\Cursos\Index as CursosIndex;
use App\Livewire\Academico\Grados\Index as GradosIndex;
use App\Livewire\Academico\Institucion\Edit as InstitucionEdit;
use App\Livewire\Academico\Niveles\Index as NivelesIndex;
use App\Livewire\Academico\Secciones\Index as SeccionesIndex;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\EscalaCalificacion;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Seccion;
use Database\Factories\AsignacionDocenteFactory;
use Database\Seeders\EscalasCalificacionSeeder;
use Livewire\Livewire;

it('configura una institución nueva siguiendo el quickstart y respeta cada regla', function () {
    $inicio = microtime(true);
    $this->seed(EscalasCalificacionSeeder::class);
    $admin = usuarioConRolDeSeeder('Administrador');
    $docente = AsignacionDocenteFactory::docenteElegible();

    // 0. Catálogo de niveles y grados.
    $niveles = Livewire::actingAs($admin)->test(NivelesIndex::class);
    $niveles->set('nombre', 'Nivel A')->set('orden', '1')->call('guardar')->assertHasNoErrors();
    $nivel = Nivel::firstOrFail();

    // 1. Institución: sin nivel se rechaza indicando el campo; con nivel se guarda.
    Livewire::actingAs($admin)->test(InstitucionEdit::class)
        ->set('nombre', 'Colegio de prueba')->set('codigo_modular', '123')->set('ugel_dre', 'UGEL X')->set('niveles', [])
        ->call('guardar')->assertHasErrors('niveles')
        ->set('niveles', [(string) $nivel->id])->set('escala_calificacion_id', EscalaCalificacion::where('codigo', 'literal')->value('id'))
        ->call('guardar')->assertHasNoErrors();

    // 2. Año con 4 bimestres: el segundo superpuesto se rechaza; corregido, se guarda.
    $periodos = fn (string $segundoInicio) => [
        ['nombre' => 'I Bimestre', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-05-15'],
        ['nombre' => 'II Bimestre', 'fecha_inicio' => $segundoInicio, 'fecha_fin' => '2026-07-31'],
        ['nombre' => 'III Bimestre', 'fecha_inicio' => '2026-08-15', 'fecha_fin' => '2026-10-15'],
        ['nombre' => 'IV Bimestre', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-12-20'],
    ];
    Livewire::actingAs($admin)->test(AnioForm::class)
        ->set('nombre', '2026')->set('fecha_inicio', '2026-03-01')->set('fecha_fin', '2026-12-20')
        ->set('periodos', $periodos('2026-05-10'))->call('guardar')->assertHasErrors('general')
        ->set('periodos', $periodos('2026-05-20'))->call('guardar')->assertHasNoErrors();

    // 3. Marcar vigente 2026 y luego 2027: queda uno solo.
    $anio26 = AnioEscolar::where('nombre', '2026')->firstOrFail();
    $anio27 = AnioEscolar::factory()->conPeriodos()->create(['nombre' => '2027', 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-12-20']);
    $lista = Livewire::actingAs($admin)->test(AniosIndex::class);
    $lista->call('marcarVigente', $anio26->id);
    expect(AnioEscolar::where('vigente', 1)->pluck('id')->all())->toBe([$anio26->id]);
    $lista->call('marcarVigente', $anio27->id)->call('marcarVigente', $anio26->id);
    expect(AnioEscolar::where('vigente', 1)->pluck('id')->all())->toBe([$anio26->id]);

    // 4. Grados, secciones A–C y un curso con varios grados; un grado de otro nivel se rechaza.
    $grados = Livewire::actingAs($admin)->test(GradosIndex::class);
    foreach ([1, 2, 3] as $n) {
        $grados->set('nivel_id', (string) $nivel->id)->set('nombre', "{$n}°")->set('orden', (string) $n)->call('guardar')->assertHasNoErrors();
    }
    $gradosCreados = Grado::orderBy('orden')->get();
    foreach ($gradosCreados as $grado) {
        Livewire::actingAs($admin)->test(SeccionesIndex::class)
            ->set('grado_id', (string) $grado->id)->set('anio_escolar_id', (string) $anio26->id)->set('identificador', 'A, B, C')
            ->call('guardar')->assertHasNoErrors();
    }
    $ajeno = Grado::factory()->create();
    Livewire::actingAs($admin)->test(CursoForm::class)
        ->set('nivel_id', (string) $nivel->id)->set('nombre', 'Curso 1')->set('grados', [(string) $ajeno->id])->call('guardar')->assertHasErrors('grados.0');
    foreach (['Curso 1', 'Curso 2', 'Curso 3'] as $nombre) {
        Livewire::actingAs($admin)->test(CursoForm::class)
            ->set('nivel_id', (string) $nivel->id)->set('nombre', $nombre)->set('grados', $gradosCreados->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('guardar')->assertHasNoErrors();
    }

    // 5. Tres cursos de 1.° A a un docente en una operación; con uno inválido no se crea ninguno.
    $seccion = Seccion::where('grado_id', $gradosCreados[0]->id)->where('identificador', 'A')->firstOrFail();
    $cursos = Curso::orderBy('nombre')->get();
    $cursos[2]->update(['activo' => false]);
    Livewire::actingAs($admin)->test(AsignacionForm::class)
        ->set('seccion_id', (string) $seccion->id)->set('cursos', $cursos->pluck('id')->map(fn ($id) => (string) $id)->all())->set('docente_id', (string) $docente->id)
        ->call('guardar')->assertHasErrors('general');
    expect(AsignacionDocente::count())->toBe(0);
    $cursos[2]->update(['activo' => true]);
    Livewire::actingAs($admin)->test(AsignacionForm::class)
        ->set('seccion_id', (string) $seccion->id)->set('cursos', $cursos->pluck('id')->map(fn ($id) => (string) $id)->all())->set('docente_id', (string) $docente->id)
        ->call('guardar')->assertHasNoErrors();
    expect(AsignacionDocente::count())->toBe(3);

    // 6. Eliminar un curso con asignaciones: impedido, y la asignación sigue visible.
    Livewire::actingAs($admin)->test(CursosIndex::class)->call('eliminar', $cursos[0]->id)->assertHasErrors('general');
    expect(Livewire::actingAs($admin)->test(AsignacionesIndex::class)->viewData('asignaciones')->total())->toBe(3);

    $segundos = round(microtime(true) - $inicio, 2);
    fwrite(STDERR, "\n[recorrido quickstart §2 automatizado: {$segundos} s]\n");
    expect($segundos)->toBeLessThan(60);
});
