# Despliegue y vuelta atrás (T058, T062–T065, DC-004)

Horas de Lima (UTC−5). Servidor `sge-staging` en B2ats_v2 (1 GB), `PERFIL_TAMANO=diario`.

## Primer despliegue de v0.2.0 (T058)

Hecho el 21/10/2026 con `./deploy/deploy.sh v0.2.0` como `sge` desde `/opt/sge` (log: `~/deploy-v0.2.0.log` en el servidor).

| Verificación | Resultado |
|---|---|
| Duración | **81 s** informados por `deploy.sh` (DC-004: < 15 min) — incluye la descarga de imágenes de GHCR |
| Migraciones | `create_roles_table`, `create_permisos_table`, `create_rol_permiso_table`, `create_usuarios_table`: todas DONE |
| Contenedores | `db`, `redis`, `app`, `web`: Healthy/Started |
| Comprobación de salud | 17:04:44, `https://sge-peru.chilecentral.cloudapp.azure.com/up` con `version=v0.2.0` |
| Navegador | `/up` → `{"status":"ok","version":"v0.2.0"}`, certificado válido, la raíz muestra el inicio de sesión |

Incidencia previa al despliegue: la comprobación de marcadores `<...>` de `lib.sh` contaba los comentarios de la
plantilla del `.env`; se corrigió en el repositorio (commit `174d11d`) y, como `v0.2.0` conserva el `lib.sh` antiguo,
se borraron del `.env` del servidor esas líneas de comentario antes de desplegar.

Siembra (21/10/2026): `docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --force`.

| Verificación | Resultado |
|---|---|
| `RolesYPermisosSeeder` / `AdministradorInicialSeeder` | DONE (388 ms / 253 ms) |
| Conteos (`tinker`) | roles=5 · permisos=7 · usuarios=1 (sin usuarios demo: `APP_ENV=staging`) |
| Inicio de sesión del Administrador | Correcto; la pantalla de inicio muestra la bienvenida y los accesos a Usuarios, Roles y Permisos |

## Estado antes de T062 (línea base)

| Dato | Valor |
|---|---|
| Fecha | 22/10/2026 (hora de Lima, UTC−5) |
| Versión activa (`/up` y `./deploy/deploy.sh --estado`) | `v0.2.0`; pila estable desde hacía 16 h |
| Usuarios (`tinker`) | 11 |
| Respaldo previo manual (`backup.sh manual`) | `sge-20261022-085943-manual.sql.gz`, 2796 bytes, 0600, `gzip -t` correcto |

El respaldo `diario` de las 03:00 de ese día (`sge-20261022-030002-diario.sql.gz`, 0600) ya existía: el cron funciona (adelanta T066).

## Despliegue de v0.2.1 (T062)

| Verificación | Resultado |
|---|---|
| Hora de inicio / duración informada (DC-004: < 15 min) | 09:05 · **27 s** |
| Respaldo `pre-v0.2.1` creado | `sge-20261022-090534-pre-v0.2.1.sql.gz`, 2797 bytes, 0600 |
| Migraciones | «Nothing to migrate» (sin cambios de esquema entre v0.2.0 y v0.2.1) |
| `/up` informa `v0.2.1` | Sí (`deploy.sh` y `--estado`) |
| Usuarios siguen existiendo (mismo conteo) | 11 = 11 |
| Inicio de sesión del Administrador | Correcto (navegador) |

## Vuelta atrás a v0.2.0 (T063)

| Verificación | Resultado |
|---|---|
| Hora de inicio / duración informada (DC-004: < 10 min) | 09:15 · **22 s** |
| `/up` informa `v0.2.0` | Sí |
| Datos intactos (conteo de usuarios) | 11 = 11 |
| `--estado` refleja v0.2.0 | Sí (activa y servida por `/up`) |
| `/var/log/sge/despliegues.log` registra ambos pasos | Sí: v0.2.0 (81 s) → v0.2.1 (27 s) → v0.2.0 (22 s), todas OK; respaldo `pre-v0.2.0` creado (`sge-20261022-091502-pre-v0.2.0.sql.gz`) |

## Fallo controlado (T064)

| Verificación | Resultado |
|---|---|
| `./deploy/deploy.sh v9.9.9`: mensaje de error y código de salida | 09:20 · «La etiqueta v9.9.9 no existe en Git (¿ya la subiste con git push origin v9.9.9?)», código de salida **1** |
| Contenedores sin cambios y `/up` sigue en `v0.2.0` | `app` y `web` seguían con la imagen `v0.2.0` y sin recrearse; `/up` → `{"status":"ok","version":"v0.2.0"}` |
| Reinicio de la VM desde el portal: la pila vuelve sola | *Restart* a las 09:36 (Lima); a los 3 min `app`, `db`, `redis` y `web` estaban en marcha (`db`, `redis` y `web` saludables) sin intervención |
| Versión activa tras el reinicio | `v0.2.0` (activa y servida por `/up`); certificado vigente (89 días); tamaño `Standard_B2ats_v2` |

## Conclusión (T065)

- DC-004: **cumplido**. El despliegue (27 s) y la vuelta atrás (22 s) están muy por debajo de 15 y 10 minutos.
- FR-008 (persistencia y reinicio), FR-011 a FR-014: **cumplidos** (versión consultable en `--estado` y `despliegues.log`, respaldo previo en cada despliegue, `/up` informa la versión).
- Nota: entre v0.2.0 y v0.2.1 no hubo migraciones, por lo que esta prueba no cubre la vuelta atrás con cambios de esquema; eso lo cubre T069 (migración fallida).
