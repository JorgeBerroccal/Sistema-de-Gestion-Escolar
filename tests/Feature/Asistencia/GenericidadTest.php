<?php

// El módulo no depende de una institución concreta ni de nombres de estado o de rol en código.

use App\Asistencia\AsistenciaService;
use App\Livewire\Asistencia\Tomar\Show;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\EstadoAsistencia;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Seccion;
use Livewire\Livewire;

it('otra institución con otros niveles y secciones toma asistencia sin cambiar código', function () {
    $e = escenarioAsistencia(0);
    $niveles = Nivel::factory()->count(2)->create();
    Institucion::factory()->create()->niveles()->sync($niveles->pluck('id'));
    $seccion = Seccion::factory()->for(Grado::factory()->create(['nivel_id' => $niveles[1]->id]))->for($e['anio'], 'anio')->create(['identificador' => 'Z9']);
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id, 'seccion_id' => $seccion->id, 'fecha_matricula' => now()->subYear()->toDateString()]);

    Livewire::actingAs($e['admin'])->test(Show::class, ['seccion' => $seccion, 'fecha' => diaHabilReciente()->toDateString()])->call('guardar')->assertHasNoErrors();

    expect(Asistencia::count())->toBe(1);
});

it('ningún nombre de nivel, grado, sección, institución ni estado de ejemplo aparece en el código del módulo', function () {
    $nombres = ['Primaria', 'Secundaria', 'Educación Inicial', 'Nivel Inicial', 'Institución Educativa Demo', 'UGEL Demo', 'Comunicación', 'Matemática'];
    $patron = '/\b('.implode('|', array_map('preg_quote', $nombres)).')\b/iu';

    $infractores = [];
    foreach (['app/Asistencia', 'app/Livewire/Asistencia', 'resources/views/livewire/asistencia', 'config/asistencia.php'] as $ruta) {
        $archivos = is_dir(base_path($ruta))
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($ruta), FilesystemIterator::SKIP_DOTS))
            : [new SplFileInfo(base_path($ruta))];
        foreach ($archivos as $archivo) {
            if ($archivo->isFile() && preg_match($patron, file_get_contents($archivo->getPathname()), $coincidencia)) {
                $infractores[] = $archivo->getPathname().' → '.$coincidencia[0];
            }
        }
    }

    expect($infractores)->toBeEmpty();
});

it('los servicios no nombran estados de asistencia: justificar y revertir salen de los datos', function () {
    $codigo = file_get_contents(base_path('app/Asistencia/AsistenciaService.php')).file_get_contents(base_path('app/Asistencia/ResumenAsistencia.php'));

    expect($codigo)->not->toMatch('/[\'"](presente|tarde|falta|falta_justificada)[\'"]/');
});

it('la zona horaria de la institución viene de configuración y la global sigue en UTC', function () {
    expect(config('asistencia.zona_horaria'))->toBe('America/Lima')->and(config('app.timezone'))->toBe('UTC')
        ->and(file_get_contents(base_path('config/asistencia.php')))->toContain("env('ASISTENCIA_ZONA_HORARIA'");
    config(['asistencia.zona_horaria' => 'Europe/Madrid']);
    expect(app(AsistenciaService::class)->hoy())->toBe(now('Europe/Madrid')->toDateString());
    expect(EstadoAsistencia::count())->toBeGreaterThanOrEqual(0);
});
