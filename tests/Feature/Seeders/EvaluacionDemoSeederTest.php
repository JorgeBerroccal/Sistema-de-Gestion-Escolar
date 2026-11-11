<?php

use App\Models\Alumno;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\EscalaCalificacion;
use App\Models\Institucion;
use App\Models\Nota;
use App\Models\Permiso;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\EvaluacionDemoSeeder;
use Database\Seeders\MatriculaDemoSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\UsuariosDemoSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]);
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(AdministradorInicialSeeder::class);
    $this->seed(UsuariosDemoSeeder::class);
    $this->seed(AcademicoDemoSeeder::class);
    $this->seed(MatriculaDemoSeeder::class);
});

it('carga criterios y notas ficticios de los alumnos de ejemplo, cierra solo la primera sección y es idempotente', function () {
    $this->seed(EvaluacionDemoSeeder::class);
    $conteos = [CriterioEvaluacion::count(), Nota::count(), CierrePeriodo::count()];
    $this->seed(EvaluacionDemoSeeder::class);

    expect($conteos[0])->toBeGreaterThan(0)->and($conteos[1])->toBeGreaterThan(0)->and($conteos[2])->toBe(1)
        ->and([CriterioEvaluacion::count(), Nota::count(), CierrePeriodo::count()])->toBe($conteos);

    foreach (CriterioEvaluacion::all()->groupBy(fn ($c) => $c->seccion_id.'-'.$c->curso_id) as $criterios) {
        expect((float) $criterios->sum('peso'))->toBe(100.0);
    }
});

it('usa la escala de la institución: con escala literal las notas son letras de esa escala', function () {
    $this->seed(EvaluacionDemoSeeder::class);

    $letras = array_keys(Institucion::actual()->escala->equivalentes);

    expect(Nota::pluck('valor')->unique()->diff($letras)->all())->toBeEmpty()
        ->and(Nota::pluck('valor')->unique()->count())->toBeGreaterThan(1);
});

it('con escala numérica las notas son números dentro del rango', function () {
    Institucion::actual()->update(['escala_calificacion_id' => EscalaCalificacion::where('codigo', 'vigesimal')->value('id')]);

    $this->seed(EvaluacionDemoSeeder::class);

    expect(Nota::where('valor_numerico', '<', 0)->orWhere('valor_numerico', '>', 20)->count())->toBe(0)
        ->and(Nota::count())->toBeGreaterThan(0);
});

it('solo califica alumnos del rango ficticio reservado y nunca crea usuarios', function () {
    $usuarios = Usuario::count();

    $this->seed(EvaluacionDemoSeeder::class);

    expect(Usuario::count())->toBe($usuarios);
    foreach (Alumno::whereIn('id', Nota::pluck('alumno_id'))->get() as $alumno) {
        expect($alumno->numero_documento)->toStartWith(MatriculaDemoSeeder::PREFIJO_DOCUMENTO);
    }
});

it('se rechaza en producción y otros entornos porque son datos personales de menores', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new EvaluacionDemoSeeder)->run())->toThrow(RuntimeException::class, $entorno);
    expect(Nota::count())->toBe(0);
})->with(['production', 'qa', 'prod']);

it('falla con un mensaje claro si no hay cuenta que gestione notas', function () {
    DB::table('rol_permiso')->whereIn('permiso_id', Permiso::where('identificador', 'notas.gestionar')->select('id'))->delete();

    expect(fn () => $this->seed(EvaluacionDemoSeeder::class))->toThrow(RuntimeException::class, 'gestionar notas');
});
