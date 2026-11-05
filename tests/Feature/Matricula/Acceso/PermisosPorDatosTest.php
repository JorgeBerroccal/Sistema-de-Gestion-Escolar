<?php

use App\Models\Alumno;
use App\Models\Permiso;

it('quitar matricula.ver_todas al Director desde la gestión de roles corta su acceso sin cambiar código', function () {
    $admin = usuarioConRolDeSeeder('Administrador');
    $director = usuarioConRolDeSeeder('Director');
    $alumno = Alumno::factory()->create();

    $this->actingAs($director)->get(route('matricula.alumnos.index'))->assertOk();
    $this->actingAs($director)->get(route('matricula.alumnos.show', $alumno))->assertOk();

    $permiso = Permiso::where('identificador', 'matricula.ver_todas')->firstOrFail();
    $director->rol->permisos()->detach($permiso->id);

    $this->actingAs($director->fresh())->get(route('matricula.alumnos.index'))->assertForbidden();
    $this->actingAs($director->fresh())->get(route('matricula.alumnos.show', $alumno))->assertForbidden();
    $this->actingAs($director->fresh())->get(route('matricula.nominas.index'))->assertForbidden();
    expect(Alumno::query()->visiblePara($director->fresh())->count())->toBe(0);
});

it('dar matricula.ver_todas a otro rol le da acceso de consulta, sin tocar código', function () {
    $alumno = Alumno::factory()->create();
    $rolNuevo = usuarioConPermisos(['matricula.ver_todas']);

    $this->actingAs($rolNuevo)->get(route('matricula.alumnos.show', $alumno))->assertOk();
    $this->actingAs($rolNuevo)->get(route('matricula.alumnos.create'))->assertForbidden();
});
