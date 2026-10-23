# Contrato: scripts de operación en el servidor (`/opt/sge/deploy/`)

Todos son Bash con `set -euo pipefail`, se ejecutan como el usuario `sge` desde `/opt/sge`, escriben a
stdout/stderr y devuelven código ≠ 0 si fallan. Ninguno imprime secretos.

## `bootstrap-servidor.sh` (una sola vez, como `azureuser` con sudo)

Entradas: `FQDN` (argumento), `EMAIL_LE` (argumento, correo para Let's Encrypt).
Hace: zona horaria `America/Lima`; swap 2 GB (`/swapfile`, `swappiness=10`, persistente);
actualizaciones automáticas de seguridad; Docker Engine + Compose v2 (repositorio oficial) con rotación
de logs; usuario `sge` (clave autorizada copiada de `azureuser`, grupo `docker`); SSH solo con clave y
sin root; `ufw` (22, 80, 443); `fail2ban` (sshd); certbot (apt) y emisión del certificado
(`--standalone`) + `reconfigure` a webroot + gancho de recarga; clon del repositorio en `/opt/sge`;
`/var/backups/sge`, `/var/log/sge`; cron de respaldo (`deploy/cron-sge`). Idempotente.
Salida: resumen de verificación (swap, ufw, fail2ban, certificado, fecha de expiración).

## `deploy.sh <etiqueta> | --reconfigurar | --estado`

| Forma | Efecto |
|---|---|
| `deploy.sh v0.2.0` | Despliega la etiqueta (pasos de `research.md` D-04). |
| `deploy.sh --reconfigurar` | Recrea contenedores con el perfil actual de `.env` sin cambiar de versión. |
| `deploy.sh --estado` | Imprime versión activa, tamaño de VM, estado de contenedores y días a la expiración del certificado. |

Verificación de versión: `GET https://<fqdn>/up` responde JSON `{"status":"ok","version":"<etiqueta>"}`
(ver `salud.md`); el despliegue es exitoso solo si `version` == etiqueta solicitada. `SGE_VERSION` se
pasa a Compose por entorno durante el despliegue y solo se escribe en `.env` **después** de que la
verificación pase, de modo que un fallo (p. ej. migración defectuosa) deja la versión anterior como
activa y registrada.

Precondiciones: `/opt/sge/.env` existe (0600) y no tiene `APP_KEY` vacío; la etiqueta existe en Git y
sus imágenes en GHCR (si no: error claro antes de tocar nada). Postcondición exitosa: `GET /up` = 200 con la versión pedida,
`VERSION` actualizado, línea en `despliegues.log`, `php artisan optimize` ejecutado. Fallo (incluida una migración que falla, que se ejecuta antes de recrear `app`/`web`): sale ≠ 0, deja
los contenedores anteriores en marcha e
imprime el comando exacto de vuelta atrás (`./deploy/deploy.sh <anterior>`) y el respaldo `pre-<etiqueta>`.
Avisa si el tamaño de VM es B2als_v2 y `PERFIL_TAMANO=diario` (o a la inversa).

## `backup.sh [origen]`

`origen` ∈ `diario` (default) | `manual` | `pre-<etiqueta>`. Ejecuta `mysqldump` dentro del contenedor
`db` con su propia `MYSQL_ROOT_PASSWORD` (sin pasarla por la línea de comandos del host). Crea
`/var/backups/sge/sge-AAAAMMDD-HHMMSS-<origen>.sql.gz` (0600), verifica que no esté vacío y que
`gzip -t` pase, borra los de más de 7 días, imprime la ruta y el tamaño.

## `restore.sh <archivo.sql.gz>`

Usa la contraseña root solo dentro del contenedor `db`, nunca en el de la aplicación (FR-032). Pide
confirmación escribiendo `RESTAURAR`. Modo mantenimiento → detiene `app` y `web` → recrea la base
`sge` → carga el volcado → `up -d` → `GET /up` → sale del mantenimiento. Imprime el número de usuarios
restaurados como comprobación. No toca Redis (las sesiones previas se invalidan).

## `metrics.sh start | stop | tamano | cierre`

- `start`: lanza en segundo plano un muestreo cada 5 s hacia `/var/log/sge/metricas-AAAAMMDD-HHMM.csv`
  con columnas `ts,cpu_pct,load1,mem_usada_mb,mem_total_mb,swap_usada_mb,ctr_app_cpu,ctr_app_mem_mb,ctr_db_cpu,ctr_db_mem_mb,ctr_redis_cpu,ctr_redis_mem_mb,ctr_web_cpu,ctr_web_mem_mb`
  y escribe `meta-servidor.json` (`vm_size`, vCPU, RAM, modelo de CPU de `/proc/cpuinfo` —Basv2 corre sobre AMD EPYC Milan—, versión desplegada, perfil, inicio).
- `stop`: detiene el muestreo, agrega `fin` a `meta-servidor.json`, imprime rutas y los máximos/p95
  de CPU y memoria para copiarlos al registro.
- `tamano`: imprime `vmSize` del servicio de metadatos de Azure (`169.254.169.254`).
- `cierre`: lee `/var/log/sge/tamano.log` (registro de tamaño: lo escribe un cron al arrancar y cada
  5 min con `vmSize`). Si, posterior al `stop` de la última prueba, la última línea es
  `Standard_B2ats_v2`, escribe `/var/log/sge/cierre-carga.json` (`evidencia_completa: true`, horas
  totales en B2als_v2 —incluida la espera para acumular créditos—, costo estimado) y sale 0. Si no, imprime `EVIDENCIA INCOMPLETA` y sale 1.
- Descarga: `scp sge@<fqdn>:/var/log/sge/metricas-*.csv .`

## `creditos-cpu.sh <desde-utc> <hasta-utc> [<inicio-rampa-utc>]` (se ejecuta en el PC, no en el servidor)

Requiere `az login` y `python3`. Consulta Azure Monitor (`az monitor metrics list`, intervalo `PT1M`, agregación
*Average*) de la VM `SGE_VM` (default `sge-staging`) en `SGE_RG` (default `rg-sge-staging`) para
«CPU Credits Remaining», «CPU Credits Consumed» y «Percentage CPU». Escribe `creditos-cpu-AAAAMMDD-HHMM.csv`
(una fila por minuto) y `creditos-resumen.json` con: créditos al inicio de la observación y al iniciar la
rampa, mínimo y cuándo ocurrió, créditos al final, `creditos_agotados` (≤ `SGE_UMBRAL_AGOTADO`, default 0,5),
`primer_minuto_agotado_utc`, `minutos_desde_el_inicio_de_la_rampa` y `minutos_con_cpu_limitada`. Imprime si los
créditos se agotaron y desde qué minuto la CPU quedó limitada a su base. Sale ≠ 0 si Azure Monitor no devuelve
la métrica (retraso de ≥ 10 min, VM liberada o métrica inexistente para el tamaño). `SGE_AZ_JSON` permite
ensayarlo con una respuesta guardada, sin Azure.
