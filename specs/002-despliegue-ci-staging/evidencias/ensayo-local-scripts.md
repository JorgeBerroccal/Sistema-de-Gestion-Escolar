# Ensayo local de deploy.sh, backup.sh y restore.sh (T033)

Fecha: 2026-10-16 · Pila de producción local (`docker-compose.prod.yml`, proyecto `sgeensayo`, certificado
autofirmado), imágenes locales etiquetadas `v0.0.1-local`, `v0.0.2-local` y `v0.0.3-falla` (esta última con
una migración que añade una columna y luego lanza una excepción). Variables de ensayo: `SGE_DIR`,
`SGE_BACKUP_DIR`, `SGE_LOG_DIR`, `SGE_HEALTH_URL`, `SGE_CURL_OPTS=-k`, `SGE_SKIP_GIT=1`, `SGE_SKIP_PULL=1`,
`SGE_VMSIZE_FILE`.

| # | Escenario | Resultado |
|---|---|---|
| a | Primer despliegue en volumen nuevo | OK en 57 s; «Primer despliegue: no hay respaldo previo»; `/up` → `v0.0.1-local` |
| b | Despliegue de `v0.0.2-local` | Respaldo `pre-v0.0.2-local` creado, migraciones sin cambios, `/up` → `v0.0.2-local` (28 s) |
| c | Vuelta atrás a `v0.0.1-local` | `/up` → `v0.0.1-local`, los 2 usuarios siguen (31 s) |
| d | Etiqueta sin imágenes (`v9.9.9`) | Error «No existe la imagen …» antes de tocar nada; `/up` sigue en `v0.0.1-local` |
| e | Respaldo manual → borrar un usuario → `restore.sh` | Archivo 0600; restauración en 14 s; el usuario borrado vuelve (2 usuarios). Sin escribir `RESTAURAR` cancela |
| f | Despliegue con migración fallida (`v0.0.3-falla`) | Respaldo `pre-…` creado; la migración falla (la columna quedó añadida); `app`/`web` anteriores siguen sirviendo `v0.0.1-local`; `VERSION` y `SGE_VERSION` del `.env` sin cambios; salida ≠ 0 con el comando de vuelta atrás y el respaldo previo; línea `FALLO` en `despliegues.log` |
| f′ | Recuperación: `restore.sh <pre-…>` + `deploy.sh v0.0.1-local` | Restauración 14 s + despliegue 16 s; la columna parcial desaparece; total 46 s desde el despliegue fallido |
| g | `.env` con permisos 644 | Error «debe tener permisos 600» |

## Defecto encontrado y corregido durante el ensayo

En el **primer despliegue sobre un volumen nuevo** la migración falló con
`SQLSTATE[HY000] [2002] Connection refused`: el healthcheck de MySQL hacía `mysqladmin ping` por socket,
y durante la inicialización del volumen MySQL arranca un servidor temporal sin red que responde por
socket, así que `db` figuraba «healthy» antes de aceptar conexiones TCP. Corregido en
`docker-compose.prod.yml` con `mysqladmin ping -h 127.0.0.1` (TCP). Reproducido antes de corregir y
verificado después (primer despliegue limpio en 57 s).

Esto habría roto el primer despliegue real en Azure. Los ensayos con Azure (cambio de tamaño, certificado,
tiempos reales) siguen pendientes de la Parte B.
