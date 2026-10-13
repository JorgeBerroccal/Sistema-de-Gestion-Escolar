<?php

// SC-005: el acceso se decide solo por permisos, nunca por nombre de rol.
// Si los módulos 2–6 necesitan 'Alumno'/'Docente' como texto de interfaz, refinar el patrón para
// detectar solo comparaciones de rol, sin relajar la regla.
it('no hay nombres de rol ni comparaciones de rol en app, routes, config ni vistas', function () {
    $nombres = ['Administrador', 'Director', 'Docente', 'Padre de Familia', 'Alumno'];
    $patrones = [
        '/[\'"]('.implode('|', array_map('preg_quote', $nombres)).')[\'"]/iu',
        '/rol->nombre\s*={2,3}/',
    ];

    $infractores = [];
    foreach (['app', 'routes', 'config', 'resources/views'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.php')) {
                continue;
            }
            $contenido = file_get_contents($archivo->getPathname());
            foreach ($patrones as $patron) {
                if (preg_match($patron, $contenido)) {
                    $infractores[] = str_replace(base_path().'/', '', $archivo->getPathname());
                }
            }
        }
    }

    expect($infractores)->toBeEmpty();
});
