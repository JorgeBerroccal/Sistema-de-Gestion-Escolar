<?php

use App\Models\Permiso;
use Database\Seeders\RolesYPermisosSeeder as S;

it('define exactamente los 5 roles', function () {
    expect(array_keys(S::ROLES))->toBe(['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno']);
});

it('define exactamente los 19 permisos (7 del Módulo 1, 6 del Módulo 2 y 6 del Módulo 3)', function () {
    expect(array_keys(S::PERMISOS))->toBe([
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.desactivar',
        'roles.ver', 'permisos.gestionar', 'roles.asignar_permisos',
        'academico.ver', 'academico.gestionar',
        'asignaciones.ver_todas', 'asignaciones.ver_propias', 'asignaciones.gestionar',
        'docencia.dictar',
        'matricula.ver_todas', 'matricula.gestionar', 'matricula.ver_nomina_propia',
        'matricula.ver_propia', 'matricula.ser_alumno', 'matricula.ser_apoderado',
    ]);
});

it('define todos los permisos con el formato de identificador', function () {
    foreach (array_keys(S::PERMISOS) as $identificador) {
        expect(preg_match(Permiso::REGEX_IDENTIFICADOR, $identificador))->toBe(1, $identificador);
    }
});

it('asigna permisos a Administrador y Director según R-07 y el contrato del Módulo 2', function () {
    $porRol = fn (string $rol) => collect(S::PERMISOS)->filter(fn ($p) => in_array($rol, $p[1]))->keys()->all();

    expect($porRol('Administrador'))->toHaveCount(13)
        ->and($porRol('Director'))->toBe(['usuarios.ver', 'roles.ver', 'academico.ver', 'asignaciones.ver_todas', 'matricula.ver_todas'])
        ->and($porRol('Docente'))->toBe(['asignaciones.ver_propias', 'docencia.dictar', 'matricula.ver_nomina_propia'])
        ->and($porRol('Padre de Familia'))->toBe(['matricula.ver_propia', 'matricula.ser_apoderado'])
        ->and($porRol('Alumno'))->toBe(['matricula.ver_propia', 'matricula.ser_alumno']);
});
