<?php

use App\Evaluacion\EvaluacionIncumplida;
use App\Evaluacion\EvaluacionService;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\Curso;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Permiso;
use App\Models\Seccion;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('el docente con asignación en el par escribe; el Administrador escribe en cualquiera', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'admin' => $admin] = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);

    expect($servicio->puedeEscribir($docente, $seccion, $curso))->toBeTrue()
        ->and($servicio->puedeEscribir($admin, $seccion, Curso::factory()->create()))->toBeTrue();

    $servicio->exigirEscritura($docente, $seccion, $curso, $periodo);
    $servicio->exigirEscritura($admin, $seccion, $curso, $periodo);
});

it('el docente no escribe en otro curso de su sección ni en otra sección con el mismo curso', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'anio' => $anio] = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $otroCurso = Curso::factory()->create();
    $otraSeccion = Seccion::factory()->for($seccion->grado)->for($anio, 'anio')->create(['identificador' => 'B']);

    expect($servicio->puedeEscribir($docente, $seccion, $otroCurso))->toBeFalse()
        ->and($servicio->puedeEscribir($docente, $otraSeccion, $curso))->toBeFalse()
        ->and(fn () => $servicio->exigirEscritura($docente, $seccion, $otroCurso, $periodo))->toThrow(AccessDeniedHttpException::class);
});

it('sin permiso no escribe aunque tenga asignación, y Director, Padre y Alumno tampoco', function () {
    ['seccion' => $seccion, 'curso' => $curso] = escenarioEvaluacion(1);
    $servicio = app(EvaluacionService::class);
    $sinPermiso = usuarioConPermisos(['docencia.dictar']);
    AsignacionDocente::query()->where('seccion_id', $seccion->id)->update(['usuario_id' => $sinPermiso->id]);

    foreach ([$sinPermiso, usuarioConPermisos(['notas.ver_todas']), usuarioConPermisos(['notas.ver_propia']), usuarioConPermisos([])] as $usuario) {
        expect($servicio->puedeEscribir($usuario, $seccion, $curso))->toBeFalse();
    }
});

it('una asignación cuyo docente perdió el marcador docencia.dictar no permite escribir', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'docente' => $docente] = escenarioEvaluacion(1);
    $docente->rol->permisos()->detach(Permiso::where('identificador', 'docencia.dictar')->value('id'));

    expect(app(EvaluacionService::class)->puedeEscribir($docente->fresh(), $seccion, $curso))->toBeFalse();
});

it('con el periodo cerrado el docente no escribe y el Administrador sí', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'admin' => $admin] = escenarioEvaluacion(1);
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);
    $servicio = app(EvaluacionService::class);

    expect($servicio->periodoCerrado($seccion, $periodo))->toBeTrue()
        ->and(fn () => $servicio->exigirEscritura($docente, $seccion, $curso, $periodo))->toThrow(EvaluacionIncumplida::class);

    $servicio->exigirEscritura($admin, $seccion, $curso, $periodo);
});

it('el cierre de una sección no afecta a otra sección del mismo periodo', function () {
    ['seccion' => $seccion, 'periodo' => $periodo, 'anio' => $anio] = escenarioEvaluacion(1);
    $otra = Seccion::factory()->for($seccion->grado)->for($anio, 'anio')->create(['identificador' => 'B']);
    CierrePeriodo::factory()->create(['seccion_id' => $seccion->id, 'periodo_id' => $periodo->id]);

    expect(app(EvaluacionService::class)->periodoCerrado($otra, $periodo))->toBeFalse();
});

it('con un año que ya no es el vigente nadie escribe, ni el Administrador; la consulta se conserva', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'periodo' => $periodo, 'docente' => $docente, 'admin' => $admin, 'anio' => $anio] = escenarioEvaluacion(1);
    AnioEscolar::where('id', $anio->id)->update(['vigente' => null]);
    $servicio = app(EvaluacionService::class);

    expect(fn () => $servicio->exigirEscritura($docente, $seccion->fresh(), $curso, $periodo))->toThrow(EvaluacionIncumplida::class)
        ->and(fn () => $servicio->exigirEscritura($admin, $seccion->fresh(), $curso, $periodo))->toThrow(EvaluacionIncumplida::class)
        ->and($servicio->puedeAbrir($docente, $seccion, $curso))->toBeTrue();
});

it('un periodo de otro año se rechaza', function () {
    ['seccion' => $seccion, 'curso' => $curso, 'admin' => $admin] = escenarioEvaluacion(1);
    $ajeno = PeriodoEvaluacion::factory()->create();

    expect(fn () => app(EvaluacionService::class)->exigirEscritura($admin, $seccion, $curso, $ajeno))->toThrow(EvaluacionIncumplida::class);
});

it('la nómina activa incluye solo matriculados, ordenados por apellidos, nombres e id', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos] = escenarioEvaluacion(3);
    $alumnos[0]->update(['apellidos' => 'Zeta', 'nombres' => 'Ana']);
    $alumnos[1]->update(['apellidos' => 'Alfa', 'nombres' => 'Beto']);
    $alumnos[2]->update(['apellidos' => 'Mu', 'nombres' => 'Carla']);
    Matricula::where('alumno_id', $alumnos[2]->id)->update(['estado' => 'retirado', 'fecha_estado' => now()->toDateString()]);

    $nomina = app(EvaluacionService::class)->nominaActiva($seccion);

    expect($nomina->pluck('id')->all())->toBe([$alumnos[1]->id, $alumnos[0]->id]);
});

it('sin escala configurada en la institución se rechaza con un mensaje claro', function () {
    ['institucion' => $institucion] = escenarioEvaluacion(1);
    Institucion::whereKey($institucion->id)->update(['escala_calificacion_id' => null]);

    expect(fn () => app(EvaluacionService::class)->escala())->toThrow(EvaluacionIncumplida::class, 'escala de calificación');
});
