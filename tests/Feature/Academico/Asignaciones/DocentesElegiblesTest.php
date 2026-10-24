<?php

use App\Academico\AsignacionService;
use App\Models\Permiso;
use Database\Factories\AsignacionDocenteFactory;

it('lista solo usuarios activos con docencia.dictar', function () {
    $docente = AsignacionDocenteFactory::docenteElegible();
    $inactivo = AsignacionDocenteFactory::docenteElegible();
    $inactivo->update(['activo' => false]);
    $sinPermiso = usuarioConPermisos(['usuarios.ver']);

    $ids = app(AsignacionService::class)->docentesElegibles()->pluck('id');

    expect($ids)->toContain($docente->id)->not->toContain($inactivo->id)->not->toContain($sinPermiso->id);
});

it('un rol al que se concede el permiso por datos también aparece sin cambiar código', function () {
    $otro = usuarioConPermisos(['usuarios.ver']);
    expect(app(AsignacionService::class)->docentesElegibles()->pluck('id'))->not->toContain($otro->id);

    $otro->rol->permisos()->attach(Permiso::firstOrCreate(['identificador' => 'docencia.dictar'], ['nombre' => 'Docencia'])->id);

    expect(app(AsignacionService::class)->docentesElegibles()->pluck('id'))->toContain($otro->id);
});

it('filtra por prefijo de nombre, apellido o usuario', function () {
    $ana = AsignacionDocenteFactory::docenteElegible();
    $ana->update(['nombres' => 'Anabel', 'apellidos' => 'Quispe']);
    $luis = AsignacionDocenteFactory::docenteElegible();
    $luis->update(['nombres' => 'Luis', 'apellidos' => 'Huamán']);

    $servicio = app(AsignacionService::class);

    expect($servicio->docentesElegibles('ana')->pluck('id')->all())->toBe([$ana->id])
        ->and($servicio->docentesElegibles('Hua')->pluck('id')->all())->toBe([$luis->id])
        ->and($servicio->docentesElegibles('%')->count())->toBe(0);
});
