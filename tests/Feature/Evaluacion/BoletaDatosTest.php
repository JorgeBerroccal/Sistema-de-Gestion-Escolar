<?php

use App\Evaluacion\BoletaService;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\EstadoAsistencia;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Database\Seeders\EstadosAsistenciaSeeder;

it('arma institución, alumno, sección, cursos con notas por criterio y promedio', function () {
    $b = escenarioBoleta();

    $datos = app(BoletaService::class)->datos($b['ana'], $b['periodo'], soloPublicados: true);
    $fila = $datos['cursos'][0];

    expect($datos['institucion']->nombre)->toBe($b['institucion']->nombre)
        ->and($datos['estudiante']->id)->toBe($b['ana']->id)
        ->and($datos['seccion']->id)->toBe($b['seccion']->id)
        ->and($datos['modo'])->toBe('periodo')
        ->and($fila['curso']->id)->toBe($b['curso']->id)
        ->and($fila['criterios'])->toHaveCount(2)
        ->and($fila['criterios'][0]['nota']->valor)->toBe('15')
        ->and($fila['promedio'])->toBe(13.0)
        ->and($datos['general']['etiqueta'])->toBe('13.00')
        ->and($datos['borrador'])->toBeFalse();
});

it('un curso sin notas aparece como «sin notas»', function () {
    $b = escenarioBoleta();

    $fila = app(BoletaService::class)->datos($b['beto'], $b['periodo'], true)['cursos'][0];

    expect($fila['promedio'])->toBeNull()->and($fila['etiqueta'])->toBeNull();
    expect(view('boletas.boleta', app(BoletaService::class)->datos($b['beto'], $b['periodo'], true))->render())->toContain('sin notas');
});

it('con escala literal la boleta muestra el calificativo literal, no el número', function () {
    $b = escenarioBoleta(escala: 'literal'); // AD (4) ×0,6 + C (1) ×0,4 = 2,8 → A

    $datos = app(BoletaService::class)->datos($b['ana'], $b['periodo'], true);
    $html = view('boletas.boleta', $datos)->render();

    expect($datos['literal'])->toBeTrue()
        ->and($datos['cursos'][0]['promedio'])->toBe(2.8)
        ->and($datos['cursos'][0]['etiqueta'])->toBe('A')
        ->and($datos['general']['etiqueta'])->toBe('A')
        ->and($html)->toContain('<strong>AD</strong>')->toContain('<strong>C</strong>')
        ->and($html)->not->toContain('2.80')->not->toContain('2,8')
        ->and($html)->toContain('equivalentes numéricos configurados por la institución');
});

it('con escala numérica la boleta muestra el número', function () {
    $b = escenarioBoleta();

    $html = view('boletas.boleta', app(BoletaService::class)->datos($b['ana'], $b['periodo'], true))->render();

    expect($html)->toContain('13.00')->not->toContain('equivalentes numéricos');
});

it('incluye el resumen de asistencia del rango del periodo del Módulo 4 y «sin datos» si no hay', function () {
    $b = escenarioBoleta();
    (new EstadosAsistenciaSeeder)->run();
    $servicio = app(BoletaService::class);

    $sinDatos = view('boletas.boleta', $servicio->datos($b['ana'], $b['periodo'], true))->render();
    expect($sinDatos)->toContain('sin datos de asistencia');

    $presente = EstadoAsistencia::where('codigo', 'presente')->first();
    $falta = EstadoAsistencia::where('codigo', 'falta')->first();
    foreach ([[0, $presente], [1, $presente], [2, $presente], [3, $falta]] as [$dias, $estado]) {
        Asistencia::factory()->create([
            'seccion_id' => $b['seccion']->id, 'alumno_id' => $b['ana']->id, 'estado_id' => $estado->id,
            'fecha' => $b['periodo']->fecha_inicio->copy()->addDays($dias + 7)->toDateString(),
        ]);
    }
    // Fuera del periodo: no cuenta.
    Asistencia::factory()->create(['seccion_id' => $b['seccion']->id, 'alumno_id' => $b['ana']->id, 'estado_id' => $falta->id,
        'fecha' => $b['periodo']->fecha_fin->copy()->addDays(40)->toDateString()]);

    $datos = $servicio->datos($b['ana'], $b['periodo'], true);

    expect($datos['asistencia']['registrados'])->toBe(4)->and($datos['asistencia']['asistencias'])->toBe(3)
        ->and($datos['asistencia']['faltas'])->toBe(1)->and($datos['asistencia']['porcentaje'])->toBe(75.0)
        ->and(view('boletas.boleta', $datos)->render())->toContain('75.0 %');
});

it('la boleta anual incluye solo periodos publicados con notas, sin contar los demás como cero', function () {
    $b = escenarioBoleta();
    $p2 = PeriodoEvaluacion::factory()->create(['anio_escolar_id' => $b['anio']->id, 'orden' => 2, 'nombre' => 'Periodo 2']);
    $p3 = PeriodoEvaluacion::factory()->create(['anio_escolar_id' => $b['anio']->id, 'orden' => 3, 'nombre' => 'Periodo 3']);
    $e2 = array_merge($b, ['periodo' => $p2]);
    esquemaEvaluacion($e2);
    $criterios2 = CriterioEvaluacion::where('periodo_id', $p2->id)->orderBy('orden')->get();
    app(EvaluacionService::class)->guardarNotas($b['seccion'], $b['curso'], $p2, [$b['ana']->id => [$criterios2[0]->id => '20', $criterios2[1]->id => '20']], $b['admin']);
    CierrePeriodo::factory()->create(['seccion_id' => $b['seccion']->id, 'periodo_id' => $p2->id]);
    // Periodo 3 sin cerrar: no se incluye para la familia.

    $familia = app(BoletaService::class)->datos($b['ana'], null, soloPublicados: true);
    $admin = app(BoletaService::class)->datos($b['ana'], null, soloPublicados: false);

    expect($familia['modo'])->toBe('anual')->and($familia['periodos']->pluck('id')->all())->toBe([$b['periodo']->id, $p2->id])
        ->and($familia['cursos'][0]['promedio'])->toBe(16.5)->and($familia['borrador'])->toBeFalse()
        ->and($admin['periodos']->pluck('id')->all())->toBe([$b['periodo']->id, $p2->id, $p3->id]) // el 3 aparece «sin notas»
        ->and($admin['cursos'][0]['promedio'])->toBe(16.5)                                      // y no cuenta como cero
        ->and($admin['borrador'])->toBeTrue(); // incluye un periodo sin cerrar (el 3)
    expect($p3->id)->not->toBeIn($familia['periodos']->pluck('id')->all());
});

it('un periodo sin cerrar es borrador solo para quien lo pide sin restringir a publicados', function () {
    $b = escenarioBoleta(cerrar: false);
    $servicio = app(BoletaService::class);

    expect($servicio->datos($b['ana'], $b['periodo'], soloPublicados: true))->toBeNull();

    $datos = $servicio->datos($b['ana'], $b['periodo'], soloPublicados: false);

    expect($datos['borrador'])->toBeTrue()->and(view('boletas.boleta', $datos)->render())->toContain('BORRADOR');
});

it('un alumno trasladado: las notas quedan en la sección original y la boleta muestra la sección vigente', function () {
    $b = escenarioBoleta();
    Matricula::where('alumno_id', $b['ana']->id)->update(['estado' => 'trasladado', 'fecha_estado' => now()->toDateString()]);
    $destino = Seccion::factory()->for($b['seccion']->grado)->for($b['anio'], 'anio')->create(['identificador' => 'Z']);
    Matricula::factory()->create(['alumno_id' => $b['ana']->id, 'seccion_id' => $destino->id, 'fecha_matricula' => now()->toDateString()]);

    $datos = app(BoletaService::class)->datos($b['ana'], $b['periodo'], soloPublicados: false);

    expect($datos['seccion']->id)->toBe($destino->id)                       // sección vigente en el encabezado
        ->and($datos['cursos'][0]['promedio'])->toBe(13.0);                  // notas de la sección original
});

it('un alumno sin matrícula en el año del periodo no tiene boleta', function () {
    $b = escenarioBoleta();
    $otro = Alumno::factory()->create();

    expect(app(BoletaService::class)->datos($otro, $b['periodo'], false))->toBeNull()
        ->and(app(BoletaService::class)->datos($otro, null, false))->toBeNull();
});

it('la plantilla no lleva nombres de institución, curso ni periodo fijos', function () {
    $vista = file_get_contents(resource_path('views/boletas/boleta.blade.php'));

    expect($vista)->not->toMatch('/Primaria|Secundaria|Matemática|Comunicación|Bimestre|Trimestre/iu');
});
