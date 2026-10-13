<?php

use App\Models\Usuario;

it('acepta usernames válidos', function (string $username) {
    expect(preg_match(Usuario::REGEX_USERNAME, $username))->toBe(1);
})->with(['ana', 'ana.perez', 'a_b-c.1', fn () => str_repeat('a', 50)]);

it('rechaza usernames inválidos', function (string $username) {
    expect(preg_match(Usuario::REGEX_USERNAME, $username))->toBe(0);
})->with(['an', fn () => str_repeat('a', 51), 'ana@colegio.pe', 'Ana', 'ana perez', 'ñandu']);
