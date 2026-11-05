<?php

use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MatriculaDemoSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\UsuariosDemoSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]);
});

it('carga alumnos y matrículas ficticios y es idempotente', function () {
    $this->seed(AcademicoDemoSeeder::class);

    $this->seed(MatriculaDemoSeeder::class);
    $conteos = [Alumno::count(), Matricula::count()];
    $this->seed(MatriculaDemoSeeder::class);

    expect($conteos)->toBe([24, 24])
        ->and([Alumno::count(), Matricula::count()])->toBe($conteos)
        ->and(Matricula::where('estado', 'retirado')->count())->toBeGreaterThan(0)
        ->and(Matricula::activas()->count())->toBeGreaterThan(0);
});

it('todos los alumnos son ficticios: documentos del rango reservado, nombres de ejemplo y ningún usuario creado', function () {
    $this->seed(AcademicoDemoSeeder::class);
    $usuariosAntes = Usuario::count();

    $this->seed(MatriculaDemoSeeder::class);

    expect(Usuario::count())->toBe($usuariosAntes);
    foreach (Alumno::all() as $alumno) {
        expect($alumno->numero_documento)->toStartWith(MatriculaDemoSeeder::PREFIJO_DOCUMENTO)
            ->and($alumno->nombres)->toContain('Demo')
            ->and($alumno->usuario_id)->toBeNull();
    }
    expect(DB::table('alumno_apoderado')->count())->toBe(0);
});

it('vincula solo los usuarios de ejemplo que ya existen (local) y no crea cuentas', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(UsuariosDemoSeeder::class);
    $this->seed(AcademicoDemoSeeder::class);
    $usuariosAntes = Usuario::count();

    $this->seed(MatriculaDemoSeeder::class);
    $this->seed(MatriculaDemoSeeder::class);

    expect(Usuario::count())->toBe($usuariosAntes)
        ->and(Alumno::whereNotNull('usuario_id')->count())->toBe(1)
        ->and(DB::table('alumno_apoderado')->count())->toBe(2);
});

it('exige la carga académica previa', function () {
    expect(fn () => $this->seed(MatriculaDemoSeeder::class))->toThrow(RuntimeException::class, 'AcademicoDemoSeeder');
});

it('se rechaza en producción y otros entornos porque son datos personales de menores', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new MatriculaDemoSeeder)->run())->toThrow(RuntimeException::class, $entorno);
    expect(Alumno::count())->toBe(0);
})->with(['production', 'qa', 'prod']);

it('se permite en staging', function () {
    $this->seed(AcademicoDemoSeeder::class);
    app()->detectEnvironment(fn () => 'staging');

    $this->seed(MatriculaDemoSeeder::class);

    expect(Alumno::count())->toBe(24);
});

it('DatabaseSeeder lo carga en local', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Alumno::count())->toBe(24);
});

it('DatabaseSeeder no carga alumnos fuera de local', function () {
    app()->detectEnvironment(fn () => 'testing');

    $this->seed(DatabaseSeeder::class);

    expect(Alumno::count())->toBe(0);
});
