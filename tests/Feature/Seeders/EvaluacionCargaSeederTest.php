<?php

use App\Evaluacion\ConsultaNotas;
use App\Evaluacion\EvaluacionService;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EvaluacionCargaSeeder as S;
use Database\Seeders\LoadTestSeeder;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'testing');
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]);
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(AdministradorInicialSeeder::class);
    $this->seed(AcademicoDemoSeeder::class);
});

it('crea los datos ficticios de la sesión de carga y es idempotente', function () {
    $this->seed(S::class);
    $conteos = [Alumno::count(), Matricula::count(), Nota::count(), CriterioEvaluacion::count(), CierrePeriodo::count(), AsignacionDocente::count(), Usuario::count()];
    $this->seed(S::class);

    expect(Alumno::where('numero_documento', 'like', S::PREFIJO_DOCUMENTO.'%')->count())->toBe(S::SECCIONES * S::ALUMNOS_POR_SECCION)
        ->and(CierrePeriodo::count())->toBe(S::SECCIONES)
        ->and(AsignacionDocente::where('curso_id', Curso::where('nombre', S::CURSO)->value('id'))->count())->toBe(S::DOCENTES)
        ->and([Alumno::count(), Matricula::count(), Nota::count(), CriterioEvaluacion::count(), CierrePeriodo::count(), AsignacionDocente::count(), Usuario::count()])->toBe($conteos);
});

it('las cuentas de familia ven notas y boleta: carga0001…1000 quedan vinculadas a un alumno con periodo cerrado', function () {
    $this->seed(S::class);
    $rol = Rol::where('nombre', 'Carga')->first();

    expect($rol->permisos()->pluck('identificador')->sort()->values()->all())->toBe(['boletas.ver_propia', 'matricula.ser_alumno', 'notas.ver_propia', 'usuarios.ver'])
        ->and(Alumno::whereNotNull('usuario_id')->count())->toBe(1000);

    $usuario = Usuario::where('username', 'carga0001')->first();
    $alumno = Alumno::where('usuario_id', $usuario->id)->first();
    $consulta = app(ConsultaNotas::class);

    expect($consulta->periodosPublicados($alumno))->toHaveCount(1)
        ->and($consulta->delAlumno($alumno, $consulta->periodosPublicados($alumno)->first(), $usuario)['promedio'])->not->toBeNull();

    $this->actingAs($usuario)->get(route('boletas.descargar', [$alumno->id, $consulta->periodosPublicados($alumno)->first()->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('los 20 docentes de carga registran notas en el periodo abierto y no en el cerrado', function () {
    $this->seed(S::class);
    $docente = Usuario::where('username', 'cargadoc01')->first();
    $asignacion = AsignacionDocente::where('usuario_id', $docente->id)->first();
    $abierto = PeriodoEvaluacion::where('anio_escolar_id', $asignacion->seccion->anio_escolar_id)->orderBy('orden')->skip(1)->first();
    $cerrado = PeriodoEvaluacion::where('anio_escolar_id', $asignacion->seccion->anio_escolar_id)->orderBy('orden')->first();
    $criterios = CriterioEvaluacion::where('seccion_id', $asignacion->seccion_id)->where('periodo_id', $abierto->id)->orderBy('orden')->get();
    $nomina = app(EvaluacionService::class)->nominaActiva($asignacion->seccion);

    expect(Usuario::where('username', 'like', 'cargadoc%')->count())->toBe(S::DOCENTES)
        ->and($criterios)->toHaveCount(S::CRITERIOS)->and($nomina)->toHaveCount(S::ALUMNOS_POR_SECCION);

    $escala = Institucion::actual()->escala;
    $valor = $escala->equivalentes !== null ? array_key_first($escala->equivalentes) : '15'; // válido en la escala de la institución
    $celdas = $nomina->mapWithKeys(fn ($al) => [$al->id => $criterios->mapWithKeys(fn ($c) => [$c->id => $valor])->all()])->all();
    $this->actingAs($docente)->post(route('notas.registro.guardar', ['seccion' => $asignacion->seccion_id, 'curso' => $asignacion->curso_id]), ['periodo' => $abierto->id, 'notas' => $celdas])
        ->assertSessionHasNoErrors();

    expect(Nota::whereIn('criterio_id', $criterios->pluck('id'))->count())->toBe(S::ALUMNOS_POR_SECCION * S::CRITERIOS);

    $this->actingAs($docente)->post(route('notas.registro.guardar', ['seccion' => $asignacion->seccion_id, 'curso' => $asignacion->curso_id]), ['periodo' => $cerrado->id, 'notas' => $celdas])
        ->assertSessionHasErrors('notas');
});

it('el rol Carga original sigue con solo usuarios.ver si no se siembra la carga de evaluación', function () {
    $this->seed(LoadTestSeeder::class);

    expect(Rol::where('nombre', 'Carga')->first()->permisos()->pluck('identificador')->all())->toBe(['usuarios.ver']);
});

it('se rechaza en producción y otros entornos y no crea nada', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new S)->run())->toThrow(RuntimeException::class, $entorno);
    expect(Alumno::count())->toBe(0)->and(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
})->with(['production', 'qa', 'prod']);

it('DatabaseSeeder no la carga', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
});

it('falla con un mensaje claro si no hay año vigente', function () {
    AnioEscolar::query()->update(['vigente' => null]);

    expect(fn () => $this->seed(S::class))->toThrow(RuntimeException::class, 'año escolar vigente');
});
