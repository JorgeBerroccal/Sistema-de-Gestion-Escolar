# Respaldo y restauración de la base de datos

## Qué se respalda y cuándo

| Origen | Cuándo | Archivo |
|---|---|---|
| `diario` | Todos los días a las 03:00 (hora de Lima), cron `/etc/cron.d/sge` | `sge-AAAAMMDD-HHMMSS-diario.sql.gz` |
| `pre-<etiqueta>` | Antes de cada despliegue con base de datos en marcha (`deploy.sh`) | `sge-…-pre-v0.2.1.sql.gz` |
| `manual` | Cuando lo ejecutes: `./deploy/backup.sh manual` | `sge-…-manual.sql.gz` |

Los archivos están en `/var/backups/sge` (permisos 600), fuera del contenedor de base de datos. Se
eliminan los de **más de 7 días** en cada ejecución. El volcado usa `mysqldump --single-transaction`
(consistente sin bloquear). Redis no se respalda: solo guarda sesiones y caché.

## Restaurar

```bash
cd /opt/sge
ls -lt /var/backups/sge | head          # elegir el archivo
./deploy/restore.sh /var/backups/sge/sge-20261020-030001-diario.sql.gz
```

El script pide escribir `RESTAURAR`, detiene `app` y `web` (el sitio no responde durante la restauración),
recrea la base `sge`, carga el volcado, vacía Redis (las sesiones se invalidan), vuelve a levantar la
pila con la versión activa, comprueba `/up` e imprime el número de usuarios restaurados. Comprobar después
que el Administrador inicia sesión. Tarda menos de 15 minutos.

La contraseña root de MySQL se usa solo dentro del contenedor `db`; los scripts no la imprimen ni la
pasan por la línea de comandos del host.

## Copia fuera del servidor

Los respaldos están en el mismo disco que la base de datos: **no protegen** si se pierde la VM. Para
copiarlos al equipo:

```bash
scp -i ~/Descargas/sge-staging-key.pem "sge@<fqdn>:/var/backups/sge/sge-*-diario.sql.gz" ~/respaldos-sge/
```

Para restaurar un archivo copiado, devolverlo al servidor con `scp` a `/var/backups/sge/` y usar
`restore.sh`.

## Después de una migración fallida

Ver [desplegar-y-volver-atras.md](desplegar-y-volver-atras.md): restaurar `pre-<etiqueta>` y desplegar la
etiqueta anterior.
