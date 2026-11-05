<?php

use App\Livewire\Asistencia\Reportes\Alumno as ReporteAlumno;
use App\Livewire\Asistencia\Reportes\Index;
use App\Models\Seccion;
use Livewire\Livewire;

it('el administrador y el director ven todas las secciones; el director sin controles de edición', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'admin' => $admin] = escenarioAsistencia(1);
    $otra = Seccion::factory()->for($seccion->anio, 'anio')->create(['identificador' => 'B']);
    $director = usuarioConPermisos(['asistencia.ver_todas']);

    foreach ([$admin, $director] as $u) {
        Livewire::actingAs($u)->test(Index::class)->assertOk()->assertViewHas('secciones', fn ($s) => $s->count() === 2);
    }
    Livewire::actingAs($director)->test(Index::class)->assertDontSee('Guardar asistencia')->assertDontSee('Justificar');
});

it('el docente solo elige sus secciones y una ajena da 403', function () {
    ['seccion' => $seccion, 'alumnos' => $alumnos, 'docente' => $docente] = escenarioAsistencia(1);
    $ajena = Seccion::factory()->for($seccion->anio, 'anio')->create(['identificador' => 'B']);

    Livewire::actingAs($docente)->test(Index::class)->assertViewHas('secciones', fn ($s) => $s->pluck('id')->all() === [$seccion->id]);
    Livewire::actingAs($docente)->test(Index::class, ['seccion' => (string) $ajena->id])->assertForbidden();
    Livewire::actingAs($docente)->test(ReporteAlumno::class, ['seccion' => $ajena, 'alumno' => $alumnos[0]])->assertForbidden();
});

it('Padre, Alumno y usuarios sin permiso reciben 403', function () {
    escenarioAsistencia(1);

    foreach ([['asistencia.ver_propia'], []] as $permisos) {
        Livewire::actingAs(usuarioConPermisos($permisos))->test(Index::class)->assertForbidden();
    }
});

it('quitar asistencia.ver_todas al director corta su acceso', function () {
    escenarioAsistencia(1);
    $director = usuarioConPermisos(['asistencia.ver_todas']);
    Livewire::actingAs($director)->test(Index::class)->assertOk();

    $director->rol->permisos()->detach();

    Livewire::actingAs($director->fresh())->test(Index::class)->assertForbidden();
});
