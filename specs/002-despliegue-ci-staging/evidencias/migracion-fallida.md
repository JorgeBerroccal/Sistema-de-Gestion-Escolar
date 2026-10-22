# Ensayo de recuperación ante migración fallida (T069, FR-031, DC-012)

Horas de Lima (UTC−5), 22/10/2026. Servidor `sge-staging`, versión activa `v0.2.0`. Comandos como `sge` desde `/opt/sge`.

| # | Paso | Resultado |
|---|---|---|
| 1 | Inicio del ensayo y `./deploy/backup.sh manual` | Inicio 15:37:56; `sge-20261022-153914-manual.sql.gz`; 11 usuarios |
| 2 | `./deploy/deploy.sh v0.2.9-prueba-falla` | La migración `2026_10_16_000001_prueba_migracion_falla` falló («Migración defectuosa de ensayo»); `deploy.sh` terminó con código 1 y creó `sge-20261022-153953-pre-v0.2.9-prueba-falla.sql.gz`. `/up` sigue en `v0.2.0` y `SGE_VERSION=v0.2.0` |
| 3 | Estado parcial: `Schema::hasColumn('usuarios','columna_prueba_falla')` | `true` (el DDL no se revierte en MySQL) |
| 4 | `./deploy/restore.sh <pre-v0.2.9-prueba-falla>` y `./deploy/deploy.sh v0.2.0` | Recuperación de 15:51:14 a 15:55:21 (4 min 7 s); restauración en 15 s con 11 usuarios; `deploy.sh`: «Nothing to migrate», desplegada `v0.2.0` en 16 s |
| 5 | Verificación | `/up` = `{"status":"ok","version":"v0.2.0"}`; usuarios = 11 (igual que antes); `hasColumn` = `false`. Inicio de sesión del Administrador: _pendiente de confirmar_ |
| 6 | Tiempo total | 15:37:56 → 15:55:21 = **17 min 25 s** (< 25 min) |
| 7 | Limpieza | Etiqueta `v0.2.9-prueba-falla` borrada del repositorio local y del remoto, y sus versiones borradas de GHCR (`sge-app` y `sge-web`; quedan `v0.2.0`, `v0.2.1` y `cache`). La etiqueta del servidor la borra el usuario con `git tag -d` |

Conclusión: tras una migración fallida, la versión activa y los contenedores no cambian, y el procedimiento documentado (`restore.sh` + `deploy.sh`) devuelve la base al estado previo en 4 min 7 s.
