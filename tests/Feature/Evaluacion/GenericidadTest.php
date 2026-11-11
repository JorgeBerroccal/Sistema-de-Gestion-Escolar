<?php

use App\Evaluacion\BoletaService;
use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\CierrePeriodo;
use App\Models\EscalaCalificacion;
use App\Models\Nota;

it('una escala nueva, con otros códigos, equivalentes y umbral, funciona solo con datos: se guarda, se promedia y sale en la boleta', function () {
    // Ninguna fila ni clase del sistema conoce esta escala: se crea únicamente como dato.
    EscalaCalificacion::create([
        'codigo' => 'cualitativa', 'nombre' => 'Cualitativa',
        'valores' => ['Logro', 'Proceso', 'Inicio'],
        'equivalentes' => ['Logro' => 3, 'Proceso' => 2, 'Inicio' => 1],
        'umbral_aprobacion' => 'Proceso',
    ]);
    $e = escenarioEvaluacion(1, 'cualitativa');
    $criterios = esquemaEvaluacion($e);
    $ana = $e['alumnos']->first();
    $servicio = app(EvaluacionService::class);

    expect(fn () => $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$ana->id => [$criterios[0]->id => 'AD']], $e['admin']))
        ->toThrow(EvaluacionIncumplida::class);

    $servicio->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$ana->id => [$criterios[0]->id => 'logro', $criterios[1]->id => 'Inicio']], $e['admin']);
    CierrePeriodo::factory()->create(['seccion_id' => $e['seccion']->id, 'periodo_id' => $e['periodo']->id]);

    // Logro (3) ×0,6 + Inicio (1) ×0,4 = 2,2 → «Proceso»; umbral «Proceso» → aprobado.
    $fila = app(ConsultaNotas::class)->delAlumno($ana, $e['periodo'], usuarioConPermisos(['notas.ver_todas']))['cursos'][0];
    $html = view('boletas.boleta', app(BoletaService::class)->datos($ana, $e['periodo'], true))->render();

    expect(Nota::pluck('valor')->sort()->values()->all())->toBe(['Inicio', 'Logro'])
        ->and($fila['promedio'])->toBe(2.2)->and($fila['etiqueta'])->toBe('Proceso')->and($fila['aprobado'])->toBeTrue()
        ->and($html)->toContain('<strong>Logro</strong>')->toContain('Proceso')->not->toContain('2.20');
});

it('cambiar los equivalentes o el umbral de la escala cambia el cálculo sin tocar el código', function () {
    $e = escenarioEvaluacion(1, 'literal');
    $criterios = esquemaEvaluacion($e, [['nombre' => 'Único', 'peso' => 100]]);
    $ana = $e['alumnos']->first();
    app(EvaluacionService::class)->guardarNotas($e['seccion'], $e['curso'], $e['periodo'], [$ana->id => [$criterios[0]->id => 'B']], $e['admin']);
    $promedio = fn () => app(ConsultaNotas::class)->promediosDelPar($e['seccion'], $e['curso'], $e['periodo'])[$ana->id];

    expect($promedio())->toBe(2.0);

    // La institución redefine los equivalentes (p. ej. a una escala de 20) y el umbral: es configuración, no regla fija.
    EscalaCalificacion::where('codigo', 'literal')->update(['equivalentes' => ['AD' => 20, 'A' => 17, 'B' => 14, 'C' => 8], 'umbral_aprobacion' => 'A']);
    Nota::query()->update(['valor_numerico' => 14]); // lo guardado antes con otros equivalentes se reescribe al volver a guardar

    expect($promedio())->toBe(14.0)
        ->and($e['institucion']->fresh()->escala->umbral_aprobacion)->toBe('A')
        ->and(app(EvaluacionService::class)->calculo()->aprueba(14.0))->toBeFalse() // B (14) no alcanza el umbral A
        ->and(app(EvaluacionService::class)->calculo()->aprueba(17.0))->toBeTrue();
});

it('ningún nombre de nivel, curso, periodo, criterio ni escala de ejemplo aparece en el código del módulo', function () {
    $nombres = ['Primaria', 'Secundaria', 'Educación Inicial', 'Nivel Inicial', 'Comunicación', 'Matemática', 'Personal Social', 'Ciencia y Tecnología', 'Arte y Cultura', 'Vigesimal', 'Bimestre', 'Trimestre', 'Semestre', 'Práctica calificada', 'Examen'];
    $patron = '/\b('.implode('|', array_map('preg_quote', $nombres)).')\b/iu';
    $rutas = ['app/Evaluacion', 'app/Livewire/Evaluacion', 'app/Http/Controllers/NotasController.php', 'app/Http/Controllers/BoletaController.php',
        'resources/views/livewire/evaluacion', 'resources/views/boletas', 'app/Autorizacion/Pertenencia/Reglas/NotaRegla.php', 'app/Autorizacion/Pertenencia/Reglas/CriterioRegla.php'];

    $infractores = [];
    foreach ($rutas as $ruta) {
        $absoluta = base_path($ruta);
        $archivos = is_dir($absoluta)
            ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absoluta, FilesystemIterator::SKIP_DOTS)))
            : [new SplFileInfo($absoluta)];

        foreach ($archivos as $archivo) {
            if ($archivo->isFile() && preg_match($patron, file_get_contents($archivo->getPathname()), $coincidencia)) {
                $infractores[] = str_replace(base_path().'/', '', $archivo->getPathname()).' → '.$coincidencia[0];
            }
        }
    }

    expect($infractores)->toBeEmpty();
});

it('el cálculo no nombra códigos de escala: se rige solo por los datos de la escala', function () {
    $codigo = file_get_contents(base_path('app/Evaluacion/CalculoPromedios.php')).file_get_contents(base_path('app/Evaluacion/EvaluacionService.php'));

    expect($codigo)->not->toMatch('/[\'"](literal|vigesimal)[\'"]/');
});
