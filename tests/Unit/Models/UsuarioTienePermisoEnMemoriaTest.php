<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;

function usuarioEnMemoria(bool $activo): Usuario
{
    $rol = (new Rol)->setRelation('permisos', collect([new Permiso(['identificador' => 'usuarios.ver'])]));

    return (new Usuario(['activo' => $activo]))->setRelation('rol', $rol);
}

it('evalúa el permiso en memoria', function () {
    $usuario = usuarioEnMemoria(true);

    expect($usuario->tienePermiso('usuarios.ver'))->toBeTrue()
        ->and($usuario->tienePermiso('usuarios.crear'))->toBeFalse();
});

it('niega todo a una cuenta inactiva', function () {
    expect(usuarioEnMemoria(false)->tienePermiso('usuarios.ver'))->toBeFalse();
});
