# Respaldo y restauración en staging (T066–T068, DC-009)

Horas de Lima (UTC−5). Servidor `sge-staging` en B2ats_v2, `PERFIL_TAMANO=diario`, versión `v0.2.0`. Todos los comandos como `sge` desde `/opt/sge`.

## Respaldo programado y manual (T066)

| Verificación | Resultado |
|---|---|
| `/etc/cron.d/sge` instalado | Sí (`root:root`, 0644, 765 bytes); línea `0 3 * * * sge cd /opt/sge && ./deploy/backup.sh diario >> /var/log/sge/backup.log 2>&1` |
| Respaldo `diario` de las 03:00 del 22/10 | `sge-20261022-030002-diario.sql.gz`, 1896 bytes, 0600; `backup.log` registra «Respaldo creado» a las 03:00:03 |
| Respaldo manual | `sge-20261022-085943-manual.sql.gz`, 2796 bytes, 0600, `gzip -t` correcto |
| Respaldo `diario` del día siguiente | _ pendiente (comprobar el 23/10/2026) |

## Restauración (T067, DC-009)

Respaldo usado: `sge-20261022-091502-pre-v0.2.0.sql.gz` (09:15, último respaldo, con los usuarios ya existentes).

| Paso | Resultado |
|---|---|
| `gzip -t` del respaldo | Correcto |
| Usuarios dentro del volcado (filas del `INSERT INTO usuarios`) | **11** |
| Usuarios antes de restaurar, tras crear `prueba-restauracion` desde la interfaz | **12** (la cuenta de prueba existía) |
| `./deploy/restore.sh <respaldo>` (confirmando con `RESTAURAR`) | 10:05–10:06; el script informa **8 s**; tiempo total con `time` **15,6 s** (DC-009: < 15 min) |
| Comprobación de salud tras restaurar | `/up` informa `v0.2.0` |
| Usuarios después | **11**; la cuenta `prueba-restauracion` ya no existe |
| Inicio de sesión del Administrador | Correcto (la sesión anterior se perdió por el vaciado de Redis, como documenta `restore.sh`) |

Conclusión: la restauración devuelve la base al estado respaldado y el Administrador puede iniciar sesión (DC-009 cumplido, FR-029).

## Retención y copia al equipo (T068, FR-028)

Hora de Lima (UTC−5) del 22/10/2026; comandos como `sge` en el servidor, salvo el `scp`.

| Paso | Resultado |
|---|---|
| Estado inicial de `/var/backups/sge` | 4 respaldos reales (`diario` 03:00, `manual` 08:59, `pre-v0.2.1` 09:05, `pre-v0.2.0` 09:15) |
| Respaldo falso | `touch -d '8 days ago'` sobre `sge-19990101-000000-prueba.sql.gz` (0 bytes); fecha 14/10 |
| `./deploy/backup.sh manual` | Creó `sge-20261022-145636-manual.sql.gz` (2807 bytes, 0600) a las 14:56:37 |
| Estado final | El falso fue eliminado; los 4 reales siguen y se suma el nuevo (5 archivos) |
| Copia al equipo | Desde la laptop: `scp sge@<fqdn>:/var/backups/sge/<archivo> ~/respaldos-sge/`, seguido de `gzip -t` → correcto; permisos 0600 |

Conclusión: la retención de 7 días elimina solo lo que supera el plazo y la copia al equipo con `scp` funciona (FR-028).
