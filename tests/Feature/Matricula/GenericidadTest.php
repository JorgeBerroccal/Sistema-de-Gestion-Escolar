<?php

// SC-007: el módulo no depende de una institución concreta; sus nombres viven en datos, no en código.

use App\Livewire\Matricula\Alumnos\Show;
use App\Livewire\Matricula\Nominas\Show as Nomina;
use App\Matricula\MatriculaService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

it('otra institución con otros niveles, grados y secciones matricula sin cambiar código', function () {
    $anio = AnioEscolar::factory()->vigente()->conPeriodos()->create();
    $niveles = Nivel::factory()->count(3)->create();
    Institucion::factory()->create()->niveles()->sync($niveles->pluck('id'));
    $grados = collect([2, 4, 9])->flatMap(fn ($cantidad, $i) => Grado::factory()->count($cantidad)->create(['nivel_id' => $niveles[$i]->id]));

    foreach ($grados as $grado) {
        Seccion::factory()->count(2)->sequence(fn ($s) => ['identificador' => chr(65 + $s->index)])->create(['grado_id' => $grado->id, 'anio_escolar_id' => $anio->id]);
    }

    $admin = usuarioConPermisos(['matricula.ver_todas', 'matricula.gestionar']);
    $alumnos = Alumno::factory()->count(6)->create();
    $seccion = Seccion::where('grado_id', $grados->last()->id)->first();

    Livewire::actingAs($admin)->test(Nomina::class, ['seccion' => $seccion])
        ->set('seleccionados', $alumnos->take(4)->pluck('id')->map(fn ($id) => (string) $id)->all())->call('matricularSeleccionados')->assertHasNoErrors();
    Livewire::actingAs($admin)->test(Show::class, ['alumno' => $alumnos[5]])
        ->set('seccionId', (string) Seccion::where('grado_id', $grados->first()->id)->first()->id)->call('matricular')->assertHasNoErrors();

    expect(Matricula::count())->toBe(5)->and(Seccion::count())->toBe(30);
});

it('ningún nombre de nivel, grado, sección o institución de ejemplo aparece en el código del módulo', function () {
    $nombres = ['Primaria', 'Secundaria', 'Educación Inicial', 'Nivel Inicial', 'Institución Educativa Demo', 'UGEL Demo', 'Comunicación', 'Matemática'];
    $patron = '/\b('.implode('|', array_map('preg_quote', $nombres)).')\b/iu';

    $infractores = [];
    $rutas = ['app/Matricula', 'app/Livewire/Matricula', 'app/Models', 'app/Autorizacion', 'resources/views/livewire/matricula', 'config'];
    foreach ($rutas as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if ($archivo->isFile() && preg_match($patron, file_get_contents($archivo->getPathname()), $coincidencia)) {
                $infractores[] = str_replace(base_path().'/', '', $archivo->getPathname()).' → '.$coincidencia[0];
            }
        }
    }

    expect($infractores)->toBeEmpty();
});

it('el servicio de matrícula no conoce niveles ni grados concretos', function () {
    expect(file_get_contents(base_path('app/Matricula/MatriculaService.php')))->not->toMatch('/Primaria|Secundaria|Inicial/i');
    expect(method_exists(MatriculaService::class, 'matricular'))->toBeTrue();
});
