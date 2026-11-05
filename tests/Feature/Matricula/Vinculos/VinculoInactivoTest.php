<?php

use App\Matricula\AlumnoService;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\Permiso;

it('un apoderado desactivado conserva el vínculo pero deja de ver datos', function () {
    $alumno = Alumno::factory()->create();
    Matricula::factory()->create(['alumno_id' => $alumno->id]);
    $padre = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado']);
    app(AlumnoService::class)->agregarApoderado($alumno, $padre, 'madre');
    expect(Alumno::query()->visiblePara($padre)->count())->toBe(1);

    $padre->update(['activo' => false]);

    expect($alumno->apoderados()->count())->toBe(1)
        ->and(Alumno::query()->visiblePara($padre->fresh())->count())->toBe(0)
        ->and(Matricula::query()->visiblePara($padre->fresh())->count())->toBe(0);
});

it('un apoderado que pierde el permiso conserva el vínculo pero deja de ver datos', function () {
    $alumno = Alumno::factory()->create();
    $padre = usuarioConPermisos(['matricula.ver_propia', 'matricula.ser_apoderado']);
    app(AlumnoService::class)->agregarApoderado($alumno, $padre, 'padre');

    $padre->rol->permisos()->detach(Permiso::where('identificador', 'matricula.ver_propia')->value('id'));

    expect($alumno->apoderados()->count())->toBe(1)
        ->and(Alumno::query()->visiblePara($padre->fresh())->count())->toBe(0);
});
