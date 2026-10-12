<?php

use App\Autorizacion\ProteccionAdministracion;
use App\Models\Permiso;
use App\Models\Rol;

function permisosAdmin(): array
{
    return ProteccionAdministracion::PERMISOS_ADMINISTRACION;
}

function proteccion(): ProteccionAdministracion
{
    return new ProteccionAdministracion;
}

it('rechaza desactivar a la única cuenta administradora', function () {
    $admin = usuarioConPermisos(permisosAdmin());

    expect(proteccion()->puedeDesactivar($admin))->toBeFalse();
});

it('rechaza cambiar el rol de la única cuenta administradora a uno sin los permisos', function () {
    $admin = usuarioConPermisos(permisosAdmin());
    $rolSinPermisos = Rol::factory()->create();

    expect(proteccion()->puedeCambiarRol($admin, $rolSinPermisos))->toBeFalse();
});

it('permite cambiar el rol de la única cuenta administradora a otro con ambos permisos', function () {
    $admin = usuarioConPermisos(permisosAdmin());
    $otroRol = usuarioConPermisos(permisosAdmin())->rol;

    expect(proteccion()->puedeCambiarRol($admin, $otroRol))->toBeTrue();
});

it('rechaza quitar usuarios.editar o roles.asignar_permisos al rol de la única cuenta administradora', function (string $quitar) {
    $admin = usuarioConPermisos(permisosAdmin());
    $conservar = Permiso::where('identificador', '!=', $quitar)
        ->whereIn('identificador', permisosAdmin())->pluck('id')->all();

    expect(proteccion()->puedeSincronizarPermisos($admin->rol, $conservar))->toBeFalse();
})->with(fn () => permisosAdmin());

it('permite las tres operaciones con dos cuentas administradoras activas', function () {
    $a = usuarioConPermisos(permisosAdmin());
    $b = usuarioConPermisos(permisosAdmin());
    $rolSinPermisos = Rol::factory()->create();

    expect(proteccion()->puedeDesactivar($a))->toBeTrue()
        ->and(proteccion()->puedeCambiarRol($a, $rolSinPermisos))->toBeTrue()
        ->and(proteccion()->puedeSincronizarPermisos($a->rol, []))->toBeTrue();
});

it('permite quitar permisos a un rol compartido si queda otra cuenta administradora en otro rol', function () {
    $a = usuarioConPermisos(permisosAdmin());
    usuarioConPermisos(permisosAdmin());

    expect(proteccion()->puedeSincronizarPermisos($a->rol, []))->toBeTrue();
});

it('no cuenta una cuenta inactiva como administradora', function () {
    usuarioConPermisos(permisosAdmin(), ['activo' => false]);
    $activo = usuarioConPermisos(permisosAdmin());

    expect(proteccion()->contarCuentasAdministradoras())->toBe(1)
        ->and(proteccion()->puedeDesactivar($activo))->toBeFalse();
});

it('exige ambos permisos para contar como administradora', function () {
    usuarioConPermisos(['usuarios.editar']);
    usuarioConPermisos(['roles.asignar_permisos']);

    expect(proteccion()->contarCuentasAdministradoras())->toBe(0);
});

it('permite desactivar a un usuario que no es administrador aunque sea la única cuenta administradora existente', function () {
    usuarioConPermisos(permisosAdmin());
    $comun = usuarioConPermisos(['usuarios.ver']);

    expect(proteccion()->puedeDesactivar($comun))->toBeTrue();
});

it('excluye a un usuario y simula permisos de rol al contar', function () {
    $a = usuarioConPermisos(permisosAdmin());
    $b = usuarioConPermisos(permisosAdmin());

    expect(proteccion()->contarCuentasAdministradoras())->toBe(2)
        ->and(proteccion()->contarCuentasAdministradoras($a->id))->toBe(1)
        ->and(proteccion()->contarCuentasAdministradoras(null, [$a->rol_id => []]))->toBe(1)
        ->and(proteccion()->contarCuentasAdministradoras(null, [$a->rol_id => permisosAdmin()]))->toBe(2);
});
