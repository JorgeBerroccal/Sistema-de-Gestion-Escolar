<?php

// Una variable que la configuración lee con env() pero que docker-compose.prod.yml no pasa al contenedor `app`
// se ignora en silencio en staging/producción (ya ocurrió con la zona horaria de la institución).
// Se revisan solo los archivos de configuración propios del proyecto, no los del framework.

const CONFIGS_PROPIOS = ['asistencia', 'asistente', 'sge'];

/** Variables propias que a propósito NO llegan al contenedor de la aplicación. */
const EXCLUIDAS_DEL_CONTENEDOR = [
    'CARGA_PASSWORD' => 'solo para el perfil de carga; se pasa por la línea de comandos al sembrar',
];

function variablesDelContenedorApp(): array
{
    $lineas = explode("\n", file_get_contents(base_path('docker-compose.prod.yml')));
    $enApp = $enEnvironment = false;
    $variables = [];

    foreach ($lineas as $linea) {
        if (preg_match('/^  (\w[\w-]*):\s*$/', $linea, $m)) {
            $enApp = $m[1] === 'app';
            $enEnvironment = false;

            continue;
        }
        if (! $enApp) {
            continue;
        }
        if (preg_match('/^    (\w+):/', $linea, $m)) {
            $enEnvironment = $m[1] === 'environment';

            continue;
        }
        if ($enEnvironment && preg_match('/^      ([A-Z][A-Z0-9_]*):/', $linea, $m)) {
            $variables[] = $m[1];
        }
    }

    return $variables;
}

it('el servicio app de docker-compose.prod.yml se lee correctamente', function () {
    expect(variablesDelContenedorApp())->toContain('APP_KEY', 'ASISTENTE_PROVEEDOR');
});

it('toda variable de configuración propia llega al contenedor app', function (string $archivo) {
    preg_match_all("/env\\('([A-Z][A-Z0-9_]*)'/", file_get_contents(config_path("$archivo.php")), $m);
    $faltan = array_diff(array_unique($m[1]), variablesDelContenedorApp(), array_keys(EXCLUIDAS_DEL_CONTENEDOR));

    expect($faltan)->toBe([], "config/$archivo.php lee variables que docker-compose.prod.yml no pasa al contenedor: ".implode(', ', $faltan));
})->with(CONFIGS_PROPIOS);
