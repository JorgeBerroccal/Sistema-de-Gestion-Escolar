<?php

use App\Models\Alumno;
use App\Models\CierrePeriodo;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\AdministradorInicialSeeder;
use Database\Seeders\EvaluacionCargaSeeder as S;
use Database\Seeders\LoadTestSeeder;
use Database\Seeders\RolesYPermisosSeeder;

// CARGA_TOTAL parametriza la cantidad de cuentas de la sesión de carga (enmienda 2.4.0: 2000 usuarios).

it('LoadTestSeeder siembra CARGA_TOTAL cuentas y mantiene 1000 por defecto', function () {
    config(['sge.carga.total' => 2000]);
    $this->seed(LoadTestSeeder::class);

    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(2000)
        ->and(Usuario::where('username', 'carga2000')->exists())->toBeTrue()
        ->and(Usuario::where('username', 'carga2001')->exists())->toBeFalse();
});

it('rechaza un CARGA_TOTAL inválido antes de sembrar', function (string $valor) {
    config(['sge.carga.total' => $valor]);

    expect(fn () => (new LoadTestSeeder)->run())->toThrow(RuntimeException::class, 'CARGA_TOTAL');
    expect(Usuario::where('username', 'like', 'carga%')->count())->toBe(0);
})->with(['abc', '0', '-5', '1.5', '5001']);

it('calcula las secciones: 34 con 1000 familias y 68 con 2000', function () {
    expect(S::seccionesPara(1000))->toBe(S::SECCIONES)
        ->and(S::seccionesPara(2000))->toBe(68)
        ->and(S::seccionesPara(10))->toBe(S::DOCENTES);
});

it('EvaluacionCargaSeeder con 2000 vincula 2000 familias a un alumno con periodo cerrado', function () {
    app()->detectEnvironment(fn () => 'testing');
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123'], 'sge.carga.total' => 2000]);
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(AdministradorInicialSeeder::class);
    $this->seed(AcademicoDemoSeeder::class);
    $this->seed(S::class);

    expect(Alumno::where('numero_documento', 'like', S::PREFIJO_DOCUMENTO.'%')->count())->toBe(68 * S::ALUMNOS_POR_SECCION)
        ->and(CierrePeriodo::count())->toBe(68)
        ->and(Alumno::whereNotNull('usuario_id')->count())->toBe(2000)
        ->and(Usuario::where('username', 'like', 'carga%')->count())->toBe(2000 + S::DOCENTES);
});
