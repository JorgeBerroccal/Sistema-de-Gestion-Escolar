# Modelo de datos: estado de operación (sin cambios en la base de datos)

Este feature **no agrega tablas ni migraciones**. Las "entidades" son artefactos de operación.

| Entidad | Dónde vive | Atributos clave | Reglas |
|---|---|---|---|
| Entorno staging | Azure (grupo de recursos `rg-sge-staging`) | región, tamaño actual, IP estática, etiqueta DNS, NSG (22/80/443), disco Standard SSD | Solo staging; `APP_ENV=staging`. Tamaño ∈ {B2ats_v2, B2als_v2} |
| Versión desplegada | `/opt/sge/VERSION`, `/var/log/sge/despliegues.log` y el campo `version` de la respuesta de `/up` | etiqueta, SHA, fecha, tamaño de VM, resultado | Una línea por despliegue; la última exitosa es la activa |
| Imagen de versión | GHCR | `sge-app:<etiqueta>`, `sge-web:<etiqueta>` | Inmutables por etiqueta; nunca se despliega `latest` |
| Archivo de entorno | `/opt/sge/.env` (0600) | secretos y perfil de tamaño | Solo en el servidor; plantilla en `deploy/env.staging.example` |
| Perfil de tamaño | variable `PERFIL_TAMANO` + `FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY` | `diario` / `carga` | Cambiarlo requiere recrear contenedores |
| Ejecución de CI | GitHub Actions | commit/PR, estado de Pest y Pint | Corre en todo push y PR |
| Evidencia de carga | artefacto de Actions + `specs/001-auth-roles-rbac/evidencias/` | `k6-resumen.json`, `k6-salida.txt`, `meta.json`, `metricas-*.csv`, `meta-servidor.json`, `creditos-cpu-*.csv`, `creditos-resumen.json` | Se conservan también las ejecuciones fallidas |
| Respaldo | `/var/backups/sge/*.sql.gz` | fecha, etiqueta activa, origen (`diario`/`pre-<etiqueta>`/`manual`) | Retención 7 días; permisos 0600 |
| Registro de verificación | `specs/001-auth-roles-rbac/evidencias/staging-*.md` | paso, resultado, intento, tiempo, peor caso | SC-007 del Módulo 1: ≥5 intentos |

Transiciones de la VM: `B2ats_v2 en marcha` → (Deallocate) → `liberada` → (Resize) → `B2als_v2 liberada`
→ (Start) → `B2als_v2 en marcha` → … → vuelta a B2ats_v2. Una VM en B2als_v2 fuera de una sesión de carga
es una condición de alarma (costo). Mientras está en B2als_v2 la CPU es *burstable*: el estado «créditos de CPU» (métrica de Azure Monitor, 36 créditos/h en reposo, máx. 864, ≈ 60 al arrancar una VM nueva) debe estar ≥ 200 antes de la rampa y se registra durante toda la prueba.
