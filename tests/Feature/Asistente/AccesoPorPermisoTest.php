<?php

use App\Livewire\Asistente\Chat\Index;
use App\Models\Permiso;
use App\Models\Rol;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Auth\AuthenticationException;
use Livewire\Livewire;

it('Docente, Director y Administrador del seeder reciben 403 en la pantalla y en el envío', function () {
    $this->seed(RolesYPermisosSeeder::class);

    foreach (['Docente', 'Director', 'Administrador'] as $rol) {
        $usuario = usuarioConRolDeSeeder($rol);

        $this->actingAs($usuario)->get(route('asistente.index'))->assertForbidden();
        Livewire::actingAs($usuario)->test(Index::class)->assertForbidden();
    }
});

it('Padre de Familia y Alumno del seeder acceden', function () {
    $this->seed(RolesYPermisosSeeder::class);

    foreach (['Padre de Familia', 'Alumno'] as $rol) {
        $this->actingAs(usuarioConRolDeSeeder($rol))->get(route('asistente.index'))->assertOk();
    }
});

it('un usuario sin sesión es redirigido al login', function () {
    $this->get(route('asistente.index'))->assertRedirect(route('login'));
});

it('asignar el permiso a otro rol lo habilita sin cambios de código, y quitarlo lo bloquea', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $docente = usuarioConRolDeSeeder('Docente');
    $permiso = Permiso::where('identificador', 'asistente.usar')->first();

    $this->actingAs($docente)->get(route('asistente.index'))->assertForbidden();

    Rol::where('nombre', 'Docente')->first()->permisos()->attach($permiso->id);
    $docente->refresh()->unsetRelation('rol');
    $this->actingAs($docente->fresh())->get(route('asistente.index'))->assertOk();

    Rol::where('nombre', 'Alumno')->first()->permisos()->detach($permiso->id);
    $this->actingAs(usuarioConRolDeSeeder('Alumno'))->get(route('asistente.index'))->assertForbidden();
});

it('un enviar() con la sesión expirada no llega al proveedor', function () {
    $e = escenarioAsistente();

    $c = Livewire::actingAs($e['alumno_usuario'])->test(Index::class);
    auth()->logout();

    expect(fn () => $c->set('mensaje', 'hola')->call('enviar'))->toThrow(AuthenticationException::class)
        ->and($e['falso']->llamadas())->toBe(0);
});
