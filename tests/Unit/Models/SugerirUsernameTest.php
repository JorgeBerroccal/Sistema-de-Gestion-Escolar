<?php

use App\Models\Usuario;

it('sugiere primer nombre y primer apellido en minúsculas, sin tildes y con ñ como n', function (string $nombres, string $apellidos, string $esperado) {
    expect(Usuario::sugerirUsername($nombres, $apellidos))->toBe($esperado);
})->with([
    'ejemplo del enunciado' => ['Rosa María', 'Quispe Huamán', 'rosa.quispe'],
    'tildes' => ['José Ángel', 'Núñez Pérez', 'jose.nunez'],
    'eñe' => ['Begoña', 'Muñoz', 'begona.munoz'],
    'espacios y mayúsculas' => ['  ANA  ', ' LÓPEZ ', 'ana.lopez'],
    'solo nombres' => ['Ana', '', 'ana'],
    'solo apellidos' => ['', 'Quispe', 'quispe'],
    'caracteres no permitidos se descartan' => ["D'Angelo", "O'Brien", 'dangelo.obrien'],
]);

it('devuelve cadena vacía si no hay datos o el resultado no cumple REGEX_USERNAME', function (string $nombres, string $apellidos) {
    expect(Usuario::sugerirUsername($nombres, $apellidos))->toBe('');
})->with([
    'vacíos' => ['', ''],
    'muy corto' => ['A', ''],
    'solo símbolos' => ['***', '!!!'],
]);

it('siempre devuelve algo que cumple REGEX_USERNAME o vacío', function () {
    $largo = str_repeat('a', 80);
    $sugerido = Usuario::sugerirUsername($largo, $largo);

    expect(strlen($sugerido))->toBeLessThanOrEqual(50)
        ->and(preg_match(Usuario::REGEX_USERNAME, $sugerido))->toBe(1);
});
