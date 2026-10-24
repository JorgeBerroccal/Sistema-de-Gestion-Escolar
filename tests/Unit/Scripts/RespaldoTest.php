<?php

use Illuminate\Support\Facades\Process;

function comprobarTablas(string $contenido): int
{
    $archivo = tempnam(sys_get_temp_dir(), 'sge-respaldo-').'.sql.gz';
    file_put_contents($archivo, gzencode($contenido));

    try {
        return Process::path(base_path())
            ->run(['bash', '-c', 'set -euo pipefail; source deploy/lib.sh; respaldo_tiene_tablas "$1"', 'bash', $archivo])
            ->exitCode();
    } finally {
        @unlink($archivo);
    }
}

it('detecta las tablas en un volcado pequeño', function () {
    expect(comprobarTablas("CREATE TABLE t (a int);\nINSERT INTO t VALUES (1);\n"))->toBe(0);
});

it('detecta las tablas en un volcado grande sin fallar por SIGPIPE', function () {
    $volcado = "CREATE TABLE t (a int);\n".str_repeat("INSERT INTO t VALUES (1234567890);\n", 400000);

    expect(comprobarTablas($volcado))->toBe(0);
});

it('rechaza un volcado sin tablas', function () {
    expect(comprobarTablas(str_repeat("INSERT INTO t VALUES (1);\n", 1000)))->not->toBe(0);
});
