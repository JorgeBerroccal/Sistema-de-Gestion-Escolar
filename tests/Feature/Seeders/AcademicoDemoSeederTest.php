<?php

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Institucion;
use App\Models\Nivel;
use App\Models\Seccion;
use Database\Seeders\AcademicoDemoSeeder;
use Database\Seeders\DatabaseSeeder;

it('carga una institución de ejemplo completa en local y es idempotente', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(AcademicoDemoSeeder::class);
    $conteos = [Nivel::count(), Grado::count(), Seccion::count(), Curso::count(), AsignacionDocente::count(), AnioEscolar::count()];
    $this->seed(AcademicoDemoSeeder::class);

    expect(Institucion::actual()->exists)->toBeTrue()
        ->and(Institucion::actual()->niveles()->count())->toBe(2)
        ->and(AnioEscolar::where('vigente', 1)->count())->toBe(1)
        ->and(AnioEscolar::where('vigente', 1)->first()->periodos)->toHaveCount(4)
        ->and($conteos)->toBe([Nivel::count(), Grado::count(), Seccion::count(), Curso::count(), AsignacionDocente::count(), AnioEscolar::count()])
        ->and(Nivel::count())->toBe(2)->and(Grado::count())->toBe(11)->and(Seccion::count())->toBe(22)
        ->and(AsignacionDocente::count())->toBe(5);
});

it('no pisa una institución ni un año vigente que ya existan', function () {
    app()->detectEnvironment(fn () => 'local');
    $ficha = Institucion::factory()->create(['nombre' => 'Mi colegio']);
    $vigente = AnioEscolar::factory()->vigente()->create();

    $this->seed(AcademicoDemoSeeder::class);

    expect(Institucion::actual()->nombre)->toBe('Mi colegio')
        ->and(AnioEscolar::where('vigente', 1)->value('id'))->toBe($vigente->id)
        ->and(Seccion::where('anio_escolar_id', $vigente->id)->count())->toBe(22);
});

it('se ejecuta en staging y testing', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    $this->seed(AcademicoDemoSeeder::class);

    expect(Institucion::actual()->exists)->toBeTrue();
})->with(['staging', 'testing']);

it('se rechaza en producción y otros entornos sin crear datos', function (string $entorno) {
    app()->detectEnvironment(fn () => $entorno);

    expect(fn () => (new AcademicoDemoSeeder)->run())->toThrow(RuntimeException::class, $entorno);

    expect(Nivel::count())->toBe(0)->and(Institucion::actual()->exists)->toBeFalse();
})->with(['production', 'qa', 'prod']);

it('DatabaseSeeder lo llama solo en local', function () {
    config(['sge.admin' => ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'secreto123']]);

    $this->seed(DatabaseSeeder::class);
    expect(Nivel::count())->toBe(0);

    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);
    expect(Nivel::count())->toBe(2);
});
