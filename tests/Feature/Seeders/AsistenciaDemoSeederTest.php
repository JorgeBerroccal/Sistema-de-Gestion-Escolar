<?php

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Permiso;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\AsistenciaDemoSeeder;
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

it('carga asistencia ficticia de los alumnos de ejemplo, sin fines de semana, y es idempotente', function () {
    $this->seed(AsistenciaDemoSeeder::class);
    $total = Asistencia::count();
    $this->seed(AsistenciaDemoSeeder::class);

    expect($total)->toBeGreaterThan(0)->and(Asistencia::count())->toBe($total)
        ->and(Asistencia::with('estado')->get()->pluck('estado.codigo')->unique()->sort()->values()->all())
        ->toContain('presente', 'falta');
    foreach (Asistencia::all() as $registro) {
        expect($registro->fecha->isWeekend())->toBeFalse();
    }
});

it('solo registra alumnos del rango ficticio reservado, nunca crea usuarios y respeta los retiros', function () {
    $usuarios = Usuario::count();

    $this->seed(AsistenciaDemoSeeder::class);

    expect(Usuario::count())->toBe($usuarios);
    foreach (Alumno::whereIn('id', Asistencia::pluck('alumno_id'))->get() as $alumno) {
        expect($alumno->numero_documento)->toStartWith(MatriculaDemoSeeder::PREFIJO_DOCUMENTO);
    }
    foreach (Matricula::whereNotNull('fecha_estado')->get() as $retirada) {
        expect(Asistencia::where('alumno_id', $retirada->alumno_id)->where('fecha', '>=', $retirada->fecha_estado)->count())->toBe(0);
    }
});

it('se rechaza en producción y otros entornos porque son datos personales de menores', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new AsistenciaDemoSeeder)->run())->toThrow(RuntimeException::class, $entorno);
    expect(Asistencia::count())->toBe(0);
})->with(['production', 'qa', 'prod']);

it('falla con un mensaje claro si no hay cuenta que gestione asistencia', function () {
    DB::table('rol_permiso')->whereIn('permiso_id', Permiso::where('identificador', 'asistencia.gestionar')->select('id'))->delete();

    expect(fn () => $this->seed(AsistenciaDemoSeeder::class))->toThrow(RuntimeException::class, 'gestionar asistencia');
});
