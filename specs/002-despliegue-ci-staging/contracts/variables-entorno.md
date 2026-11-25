# Contrato: variables del `.env` del servidor (`deploy/env.staging.example`)

Valores `<…>` = secreto o dato propio del servidor (nunca en el repositorio).

## Qué recibe cada contenedor (mínimo privilegio, FR-032)

- `app`: lista explícita `environment:` en `docker-compose.prod.yml` con las variables de aplicación
  (`APP_*`, `LOG_*`, `BCRYPT_ROUNDS`, `SESSION_*`, `CACHE_STORE`, `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`,
  `REDIS_*`, `SGE_ADMIN_*`, `SGE_VERSION`, `OPCACHE_VALIDATE_TIMESTAMPS`, `FPM_*`, `PHP_MEMORY_LIMIT`).
  **No** recibe `DB_ROOT_PASSWORD` ni `MYSQL_ROOT_PASSWORD`, ni `REGISTRO`/`FQDN`.
- `db`: `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` (de `DB_PASSWORD` y
  `DB_ROOT_PASSWORD`); es el único contenedor con la clave root.
- `.env` se usa en el host solo para interpolar Compose; no se pasa completo con `env_file`.

## Aplicación

| Variable | Valor en staging |
|---|---|
| `APP_ENV` | `staging` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | `<generada en el servidor>` |
| `APP_URL` | `https://<fqdn>` |
| `APP_LOCALE` | `es` |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `info` |
| `BCRYPT_ROUNDS` | `12` |
| `SESSION_DRIVER` / `CACHE_STORE` | `redis` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_LIFETIME` | `120` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` | `db` / `3306` / `sge` / `sge` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | `<aleatorias, ≥ 24 caracteres>` |
| `REDIS_HOST` | `redis` |
| `SGE_ADMIN_USERNAME`, `SGE_ADMIN_EMAIL` | `<propios>` |
| `SGE_ADMIN_PASSWORD` | `<fuerte, ≥ 16 caracteres>` |
| `OPCACHE_VALIDATE_TIMESTAMPS` | `0` |

## Despliegue

| Variable | Valor |
|---|---|
| `REGISTRO` | `ghcr.io/<propietario>` |
| `SGE_VERSION` | la escribe `deploy.sh` (etiqueta activa) |
| `FQDN` | `<fqdn>` |
| `PERFIL_TAMANO` | `diario`, `carga` o `carga2000` |

## Perfiles de tamaño (los aplica `deploy.sh` leyendo `PERFIL_TAMANO`)

| Variable | `diario` (B2ats_v2, 1 GB) | `carga` (B2als_v2, 4 GB) | `carga2000` (B4als_v2, 8 GiB) |
|---|--:|--:|--:|
| `FPM_MAX_CHILDREN` | 4 | 20 | 40 |
| `FPM_START_SERVERS` | 2 | 6 | 12 |
| `FPM_MIN_SPARE` / `FPM_MAX_SPARE` | 1 / 3 | 4 / 10 | 8 / 20 |
| `MYSQL_BUFFER_POOL` | 128M | 1G | 2G |
| `MYSQL_MAX_CONNECTIONS` | 40 | 100 | 120 |
| `REDIS_MAXMEMORY` | 64mb | 256mb | 512mb |
| `PHP_MEMORY_LIMIT` | 128M | 256M | 256M |

Presupuesto de memoria de `carga` en 4 GB: SO+Docker ≈ 0,25 GB, MySQL ≈ 1,3 GB (pool 1G + conexiones), PHP-FPM ≈ 20 × 40 MB = 0,8 GB, Redis ≤ 0,25 GB, nginx ≈ 0,01 GB → ≈ 2,6 GB, con holgura y el swap de 2 GB como red de seguridad. El cuello de botella esperado es la CPU (bcrypt), no la memoria: 20 workers sobran para 2 vCPU. `carga2000` (Enmienda 2.4.0) está dimensionado para B4als_v2 (4 vCPU, 8 GiB; el máximo que permite la cuota regional): mantiene 10 workers por vCPU (40) y el pool de MySQL en ≈ 25 % de la RAM (2G), con 120 conexiones (3 por worker, margen para scheduler y colas). Memoria estimada ≈ 5,2 GB de 8 GiB: SO+Docker 0,25 GB, MySQL ≈ 2,4 GB (pool 2G + 120 × ≈ 3 MB), PHP-FPM ≈ 40 × 50 MB = 2 GB, Redis ≤ 0,5 GB (el doble que `carga`, por el doble de usuarios); quedan ≈ 2,5 GB libres para el sistema operativo y la caché de archivos, más 2 GB de swap. Es una estimación: se confirma con el humo y se registra el valor final en la evidencia. Los valores de `carga` se ajustan en la primera sesión según la memoria observada en `metrics.sh` (se
registra el valor final en la evidencia). En desarrollo local los defaults reproducen la configuración
actual (`max_children=20`, `buffer_pool=256M`, Redis 256 MB).
