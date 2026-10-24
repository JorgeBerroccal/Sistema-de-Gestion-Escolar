<?php

use App\Livewire\Admin\Roles\Permisos;
use App\Models\Permiso;
use App\Models\Rol;
use Livewire\Livewire;

it('quitar academico.ver a un rol desde la gestión del Módulo 1 corta su acceso', function () {
    $director = usuarioConRolDeSeeder('Director');
    $this->actingAs($director)->get('/academico/grados')->assertOk();

    $admin = usuarioConRolDeSeeder('Administrador');
    $rolDirector = Rol::where('nombre', 'Director')->first();
    $restantes = $rolDirector->permisos()->where('identificador', '!=', 'academico.ver')->pluck('permisos.id')->all();

    Livewire::actingAs($admin)->test(Permisos::class, ['rol' => $rolDirector])->set('seleccionados', $restantes)->call('guardar')->assertHasNoErrors();

    $this->actingAs($director->fresh())->get('/academico/grados')->assertForbidden();
});

it('conceder un permiso por datos abre la ruta correspondiente', function () {
    $alumno = usuarioConRolDeSeeder('Alumno');
    $this->actingAs($alumno)->get('/academico/grados')->assertForbidden();

    $alumno->rol->permisos()->attach(Permiso::where('identificador', 'academico.ver')->first());

    $this->actingAs($alumno->fresh())->get('/academico/grados')->assertOk();
});

it('Padre de Familia y Alumno reciben 403 en todas las rutas del módulo', function (string $rol) {
    $usuario = usuarioConRolDeSeeder($rol);

    foreach (['institucion', 'niveles', 'anios', 'anios/crear', 'grados', 'secciones', 'cursos', 'cursos/crear', 'asignaciones', 'asignaciones/crear', 'mis-asignaciones'] as $ruta) {
        $this->actingAs($usuario)->get("/academico/{$ruta}")->assertForbidden();
    }
})->with(['Padre de Familia', 'Alumno']);
