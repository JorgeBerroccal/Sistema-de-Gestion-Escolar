<?php

// Seguridad: ningún seeder crea usuarios con contraseñas conocidas fuera de local (staging es público).

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Seccion;
use App\Models\Usuario;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\MatriculaDemoSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Database\Seeders\UsuariosDemoSeeder;

it('en staging los seeders de ejemplo cargan datos académicos y alumnos sin crear ninguna cuenta', function () {
    app()->detectEnvironment(fn () => 'staging');

    $this->seed(AcademicoDemoSeeder::class);
    $this->seed(MatriculaDemoSeeder::class);

    expect(Usuario::count())->toBe(0)
        ->and(Institucion::actual()->exists)->toBeTrue()
        ->and(Seccion::count())->toBe(22)
        ->and(Alumno::count())->toBe(24)
        ->and(Matricula::count())->toBe(24)
        ->and(AsignacionDocente::count())->toBe(0);
});

it('en local AcademicoDemoSeeder sí crea las cuentas docente.demo* y sus asignaciones', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(AcademicoDemoSeeder::class);

    expect(Usuario::whereIn('username', ['docente.demo', 'docente.demo2', 'docente.demo3'])->count())->toBe(3)
        ->and(AsignacionDocente::count())->toBe(5);
});

it('UsuariosDemoSeeder se rechaza fuera de local sin crear usuarios', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new UsuariosDemoSeeder)->run())->toThrow(RuntimeException::class, $entorno);
    expect(Usuario::count())->toBe(0);
})->with(['staging', 'production', 'qa', 'prod']);

it('UsuariosDemoSeeder funciona en local', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed(RolesYPermisosSeeder::class);

    $this->seed(UsuariosDemoSeeder::class);

    expect(Usuario::count())->toBe(4);
});

it('ningún seeder del módulo de matrícula ni de la gestión académica crea usuarios con contraseña literal fuera de local', function () {
    foreach (['AcademicoDemoSeeder', 'MatriculaDemoSeeder'] as $seeder) {
        $codigo = file_get_contents(base_path("database/seeders/{$seeder}.php"));

        if ($seeder === 'MatriculaDemoSeeder') {
            expect($codigo)->not->toContain("'password'")->not->toContain('Usuario::create')->not->toContain('Usuario::firstOrCreate');
        } else {
            expect($codigo)->toContain("app()->environment('local')");
        }
    }
});
