<?php

use App\Models\Permiso;

it('acepta identificadores válidos', function (string $identificador) {
    expect(preg_match(Permiso::REGEX_IDENTIFICADOR, $identificador))->toBe(1);
})->with(['usuarios.ver', 'roles.asignar_permisos', 'a.b.c']);

it('rechaza identificadores inválidos', function (string $identificador) {
    expect(preg_match(Permiso::REGEX_IDENTIFICADOR, $identificador))->toBe(0);
})->with(['usuarios', 'Usuarios.ver', 'usuarios.ver.', '.ver', 'usuarios-ver', 'usuarios.ver1']);
