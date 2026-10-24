# Incidente: `backup.sh` falla con volcados grandes (23/10/2026)

## Síntoma
Durante T072 (paso 2), `./deploy/backup.sh manual` en staging terminó con el error «El respaldo no contiene tablas»,
aunque los respaldos anteriores (incluido el diario de las 03:00 del 23/10) funcionaban.

## Diagnóstico
MySQL `healthy`, la aplicación ve 1011 usuarios, sin OOM, con memoria y disco libres, y las tablas existen. Lo único que
cambió desde el día anterior fue el tamaño de la base: a las 15:47 se sembraron 1000 usuarios con `LoadTestSeeder`.

## Causa
En `deploy/backup.sh` la comprobación era `zcat "$archivo.parcial" | grep -q 'CREATE TABLE' || die …` con `set -euo pipefail`.
`grep -q` sale en la primera coincidencia; en un volcado grande `zcat` sigue escribiendo, recibe SIGPIPE y sale con 141, y
`pipefail` hace que la tubería falle. El script concluía «sin tablas» con el archivo correcto. Con volcados pequeños `zcat` ya
había terminado de escribir, por eso no ocurría antes. `restore.sh` no tiene el defecto (`zcat | mysql` lee todo el volcado).

## Reproducción (local)
Volcado de 200 000 `INSERT` más un `CREATE TABLE` al inicio, `bash -c 'set -o pipefail; zcat grande.gz | grep -q "CREATE TABLE"; echo $?'`
→ **141**. Con un volcado de dos líneas → 0.

## Arreglo
`deploy/lib.sh`: nueva función `respaldo_tiene_tablas`, que cuenta con `grep -c` (lee todo el volcado) en vez de `grep -q`;
`deploy/backup.sh` la usa. Publicado como **v0.2.2**. Sin la corrección, el respaldo diario de las 03:00 también habría fallado.

## Prueba
`tests/Unit/Scripts/RespaldoTest.php` (Pest, corre en CI): volcado pequeño (detecta tablas), volcado de 400 000 filas (detecta
tablas sin SIGPIPE) y volcado sin tablas (rechaza). El caso grande falla con la comprobación antigua y pasa con la nueva.
ShellCheck sin avisos.

## Despliegue y verificación
`deploy.sh` hace `checkout` de la etiqueta y se relanza (líneas 99–105) antes del respaldo previo (línea 137), de modo que el
respaldo `pre-v0.2.2` ya usa el `backup.sh` corregido; el cron diario (`cd /opt/sge && ./deploy/backup.sh diario`) usa el
mismo árbol. Resultado en el servidor: _pendiente (salida de `deploy.sh v0.2.2` y de `backup.sh manual`)_.
