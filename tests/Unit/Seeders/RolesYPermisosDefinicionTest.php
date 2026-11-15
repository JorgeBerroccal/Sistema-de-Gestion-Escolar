<?php

use App\Models\Permiso;
use Database\Seeders\RolesYPermisosSeeder as S;

it('define exactamente los 5 roles', function () {
    expect(array_keys(S::ROLES))->toBe(['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno']);
});

it('define exactamente los 31 permisos (7 del Módulo 1, 6 del Módulo 2, 6 del Módulo 3, 4 del Módulo 4, 7 del Módulo 5 y 1 del Módulo 6)', function () {
    expect(array_keys(S::PERMISOS))->toBe([
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.desactivar',
        'roles.ver', 'permisos.gestionar', 'roles.asignar_permisos',
        'academico.ver', 'academico.gestionar',
        'asignaciones.ver_todas', 'asignaciones.ver_propias', 'asignaciones.gestionar',
        'docencia.dictar',
        'matricula.ver_todas', 'matricula.gestionar', 'matricula.ver_nomina_propia',
        'matricula.ver_propia', 'matricula.ser_alumno', 'matricula.ser_apoderado',
        'asistencia.ver_todas', 'asistencia.gestionar', 'asistencia.registrar_propia', 'asistencia.ver_propia',
        'notas.ver_todas', 'notas.gestionar', 'notas.registrar_propia', 'notas.ver_propia', 'notas.cerrar_periodo',
        'boletas.ver_todas', 'boletas.ver_propia',
        'asistente.usar',
    ]);
});

it('define todos los permisos con el formato de identificador', function () {
    foreach (array_keys(S::PERMISOS) as $identificador) {
        expect(preg_match(Permiso::REGEX_IDENTIFICADOR, $identificador))->toBe(1, $identificador);
    }
});

it('asigna permisos a Administrador y Director según R-07 y el contrato del Módulo 2', function () {
    $porRol = fn (string $rol) => collect(S::PERMISOS)->filter(fn ($p) => in_array($rol, $p[1]))->keys()->all();

    expect($porRol('Administrador'))->toHaveCount(19)
        ->and($porRol('Director'))->toBe(['usuarios.ver', 'roles.ver', 'academico.ver', 'asignaciones.ver_todas', 'matricula.ver_todas', 'asistencia.ver_todas', 'notas.ver_todas', 'boletas.ver_todas'])
        ->and($porRol('Docente'))->toBe(['asignaciones.ver_propias', 'docencia.dictar', 'matricula.ver_nomina_propia', 'asistencia.registrar_propia', 'notas.registrar_propia'])
        ->and($porRol('Padre de Familia'))->toBe(['matricula.ver_propia', 'matricula.ser_apoderado', 'asistencia.ver_propia', 'notas.ver_propia', 'boletas.ver_propia', 'asistente.usar'])
        ->and($porRol('Alumno'))->toBe(['matricula.ver_propia', 'matricula.ser_alumno', 'asistencia.ver_propia', 'notas.ver_propia', 'boletas.ver_propia', 'asistente.usar']);
});
