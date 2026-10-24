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
mismo árbol. 
Resultado en el servidor (23/10/2026): `deploy.sh v0.2.2` terminó correctamente en 46 s («Nothing to migrate», `/up` informa
`version=v0.2.2`, 19:22:26). `./deploy/backup.sh manual` a las 19:22:42 creó `sge-20261023-192242-manual.sql.gz` (12 KB) con
1011 usuarios en la base, donde antes fallaba. El tamaño es coherente: `LoadTestSeeder` reutiliza un único hash bcrypt, así que los
1000 usuarios comprimen muy bien (12 KB frente a 2,8 KB del respaldo anterior a la siembra), y descomprimido supera el búfer de
64 KB de la tubería, que es lo que provocaba el SIGPIPE.

## Cierre (23/10/2026)
Con el servidor en **v0.2.2** (`/up` lo confirma), `./deploy/backup.sh manual` terminó correctamente:
«Respaldo creado: /var/backups/sge/sge-20261023-230742-manual.sql.gz (12K)». Incidente cerrado. Ese mismo respaldo cubre el
paso 2 de T072. Queda por comprobar mañana (24/10) que el respaldo diario de las 03:00 se creó con v0.2.2.
