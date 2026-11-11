<?php

use App\Models\Alumno;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\EvaluacionCargaSeeder;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Support\Facades\Hash;

// La contraseña de las cuentas de carga no está en el repositorio: sale de CARGA_PASSWORD y es obligatoria fuera de
// local y testing (research R-22).

beforeEach(function () {
    config(['sge.carga.password' => null]);
    putenv('CARGA_PASSWORD');
    unset($_ENV['CARGA_PASSWORD'], $_SERVER['CARGA_PASSWORD']);
});

it('en staging sin CARGA_PASSWORD se rechaza y no crea nada', function () {
    app()->detectEnvironment(fn () => 'staging');

    expect(fn () => (new LoadTestSeeder)->run())->toThrow(RuntimeException::class, 'CARGA_PASSWORD')
        ->and(Usuario::count())->toBe(0);

    app()->detectEnvironment(fn () => 'staging');
    $this->seed(AcademicoDemoSeeder::class);

    expect(fn () => (new EvaluacionCargaSeeder)->run())->toThrow(RuntimeException::class, 'CARGA_PASSWORD')
        ->and(Usuario::count())->toBe(0)->and(Alumno::where('numero_documento', 'like', '9001%')->count())->toBe(0);
});

it('en staging rechaza una contraseña de menos de 12 caracteres', function () {
    app()->detectEnvironment(fn () => 'staging');
    config(['sge.carga.password' => 'corta']);

    expect(fn () => (new LoadTestSeeder)->run())->toThrow(RuntimeException::class, 'al menos 12')
        ->and(Usuario::count())->toBe(0);
});

it('en staging usa la contraseña del entorno y no «password»', function () {
    app()->detectEnvironment(fn () => 'staging');
    config(['sge.carga.password' => 'una-contrasena-larga-1']);

    (new LoadTestSeeder)->run();
    $usuario = Usuario::where('username', 'carga0007')->first();

    expect(Hash::check('una-contrasena-larga-1', $usuario->password))->toBeTrue()
        ->and(Hash::check('password', $usuario->password))->toBeFalse();
});

it('también la lee de la variable de entorno del proceso (configuración en caché)', function () {
    app()->detectEnvironment(fn () => 'staging');
    putenv('CARGA_PASSWORD=desde-el-proceso-123');

    (new LoadTestSeeder)->run();

    expect(Hash::check('desde-el-proceso-123', Usuario::where('username', 'carga0007')->value('password')))->toBeTrue();
    putenv('CARGA_PASSWORD');
});

it('en local y testing sin variable conserva «password»', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    (new LoadTestSeeder)->run();

    expect(Hash::check('password', Usuario::where('username', 'carga0007')->value('password')))->toBeTrue();
})->with(['local', 'testing']);

it('EvaluacionCargaSeeder aplica la misma contraseña a docentes y familias en staging', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed(AcademicoDemoSeeder::class);
    app()->detectEnvironment(fn () => 'staging');
    config(['sge.carga.password' => 'una-contrasena-larga-1']);

    (new EvaluacionCargaSeeder)->run();

    foreach (['carga0001', 'cargadoc01'] as $username) {
        expect(Hash::check('una-contrasena-larga-1', Usuario::where('username', $username)->value('password')))->toBeTrue();
    }
});
