<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesYPermisosSeeder;

it('un usuario sin permisos solo ve Inicio', function () {
    $this->actingAs(usuarioConPermisos([]))->get('/inicio')
        ->assertOk()
        ->assertSee('Inicio')
        ->assertDontSee('Usuarios')
        ->assertDontSee('Roles')
        ->assertDontSee('Permisos');
});

it('con usuarios.ver se ve el enlace Usuarios hacia /admin/usuarios', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.ver']))->get('/inicio')
        ->assertOk()
        ->assertSee('href="'.url('/admin/usuarios').'"', false)
        ->assertSee('>Usuarios<', false);
});

it('sin usuarios.ver no se ve el enlace Usuarios', function () {
    $this->actingAs(usuarioConPermisos(['roles.ver']))->get('/inicio')
        ->assertOk()
        ->assertDontSee('>Usuarios<', false);
});

it('con roles.ver se ven Roles y Permisos', function () {
    $this->actingAs(usuarioConPermisos(['roles.ver']))->get('/inicio')
        ->assertOk()
        ->assertSee('>Roles<', false)
        ->assertSee('>Permisos<', false);
});

it('sin roles.ver no se ven Roles ni Permisos', function () {
    $this->actingAs(usuarioConPermisos(['usuarios.ver']))->get('/inicio')
        ->assertOk()
        ->assertDontSee('>Roles<', false)
        ->assertDontSee('>Permisos<', false);
});

it('cada rol sembrado ve los ítems de la tabla de visibilidad esperada', function (string $rol, array $visibles) {
    $this->seed(RolesYPermisosSeeder::class);
    $usuario = Usuario::factory()->create(['rol_id' => Rol::where('nombre', $rol)->value('id')]);

    $html = $this->actingAs($usuario)->get('/inicio')->assertOk()->getContent();

    foreach (['Inicio', 'Usuarios', 'Roles', 'Permisos'] as $item) {
        in_array($item, $visibles, true)
            ? expect($html)->toContain('>'.$item.'<')
            : expect($html)->not->toContain('>'.$item.'<');
    }
})->with([
    'Administrador' => ['Administrador', ['Inicio', 'Usuarios', 'Roles', 'Permisos']],
    'Director' => ['Director', ['Inicio', 'Usuarios', 'Roles', 'Permisos']],
    'Docente' => ['Docente', ['Inicio']],
    'Padre de Familia' => ['Padre de Familia', ['Inicio']],
    'Alumno' => ['Alumno', ['Inicio']],
]);
