<?php

use Database\Seeders\RolesYPermisosSeeder as S;

it('define exactamente los 5 roles', function () {
    expect(array_keys(S::ROLES))->toBe(['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno']);
});

it('define exactamente los 7 permisos base', function () {
    expect(array_keys(S::PERMISOS))->toBe([
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.desactivar',
        'roles.ver', 'permisos.gestionar', 'roles.asignar_permisos',
    ]);
});

it('asigna permisos a Administrador y Director según R-07', function () {
    $porRol = fn (string $rol) => collect(S::PERMISOS)->filter(fn ($p) => in_array($rol, $p[1]))->keys()->all();

    expect($porRol('Administrador'))->toHaveCount(7)
        ->and($porRol('Director'))->toBe(['usuarios.ver', 'roles.ver'])
        ->and($porRol('Docente'))->toBeEmpty()
        ->and($porRol('Padre de Familia'))->toBeEmpty()
        ->and($porRol('Alumno'))->toBeEmpty();
});
