<?php

// SC-007: el módulo no depende de una institución concreta; sus nombres viven en datos, no en código.

use App\Academico\AsignacionService;
use App\Livewire\Academico\Asignaciones\Index as AsignacionesIndex;
use App\Livewire\Academico\Cursos\Form as CursoForm;
use App\Livewire\Academico\Secciones\Index as SeccionesIndex;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Nivel;
use App\Models\Seccion;
use Database\Factories\AsignacionDocenteFactory;
use Livewire\Livewire;

it('otra institución con otros niveles y cantidades se configura y opera sin cambiar código', function () {
    // Una institución distinta de la de ejemplo: 2 niveles inventados, 3 y 7 grados, 5 secciones cada uno.
    $anio = AnioEscolar::factory()->vigente()->conPeriodos()->create();
    $niveles = Nivel::factory()->count(2)->create();
    Institucion::factory()->create()->niveles()->sync($niveles->pluck('id'));
    $gradosA = Grado::factory()->count(3)->create(['nivel_id' => $niveles[0]->id]);
    $gradosB = Grado::factory()->count(7)->create(['nivel_id' => $niveles[1]->id]);

    foreach ($gradosA->concat($gradosB) as $grado) {
        Seccion::factory()->count(5)->sequence(fn ($s) => ['identificador' => chr(65 + $s->index)])
            ->create(['grado_id' => $grado->id, 'anio_escolar_id' => $anio->id]);
    }

    $admin = usuarioConPermisos(['academico.ver', 'academico.gestionar', 'asignaciones.ver_todas', 'asignaciones.gestionar']);

    // Alta de un curso por la interfaz y de una sección extra.
    Livewire::actingAs($admin)->test(CursoForm::class)
        ->set('nivel_id', (string) $niveles[1]->id)->set('nombre', 'Curso inventado')
        ->set('grados', $gradosB->take(4)->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('guardar')->assertHasNoErrors();
    Livewire::actingAs($admin)->test(SeccionesIndex::class)
        ->set('grado_id', (string) $gradosA[0]->id)->set('anio_escolar_id', (string) $anio->id)->set('identificador', 'Z')
        ->call('guardar')->assertHasNoErrors();

    // Asignación del curso en una sección de uno de sus grados.
    $curso = Curso::where('nombre', 'Curso inventado')->first();
    $seccion = Seccion::where('grado_id', $gradosB[0]->id)->first();
    $docente = AsignacionDocenteFactory::docenteElegible();
    app(AsignacionService::class)->asignar($seccion, $curso, $docente);

    expect(Grado::count())->toBe(10)->and(Seccion::count())->toBe(51)->and(AsignacionDocente::count())->toBe(1);
    expect(Livewire::actingAs($admin)->test(AsignacionesIndex::class)->viewData('asignaciones')->total())->toBe(1);
});

it('ningún nombre de nivel, grado, curso ni escala de ejemplo aparece en app, vistas ni config', function () {
    $nombres = ['Primaria', 'Secundaria', 'Educación Inicial', 'Nivel Inicial', 'Comunicación', 'Matemática', 'Personal Social', 'Ciencia y Tecnología', 'Arte y Cultura', 'Vigesimal'];
    $patron = '/\b('.implode('|', array_map('preg_quote', $nombres)).')\b/iu';

    $infractores = [];
    foreach (['app', 'resources/views', 'config'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if ($archivo->isFile() && preg_match($patron, file_get_contents($archivo->getPathname()), $coincidencia)) {
                $infractores[] = str_replace(base_path().'/', '', $archivo->getPathname()).' → '.$coincidencia[0];
            }
        }
    }

    expect($infractores)->toBeEmpty();
});
