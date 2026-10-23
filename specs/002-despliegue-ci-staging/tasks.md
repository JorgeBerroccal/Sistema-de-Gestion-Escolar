---

description: "Task list for 002-despliegue-ci-staging (Despliegue en staging e integración continua)"
---

# Tasks: Despliegue en servidor staging e integración continua

**Input**: Design documents from `/specs/002-despliegue-ci-staging/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: El único código de aplicación que cambia (guarda del `LoadTestSeeder` y la ruta `/up` con la versión desplegada, sin tocar vistas) lleva pruebas Pest escritas primero (Principio V). Scripts, Dockerfiles y workflows se validan con `shellcheck`, `actionlint` y una ejecución local de la pila de producción (Fase 2); lo que depende del servidor se valida con [quickstart.md](quickstart.md).

**Organization**: Las fases están ordenadas por **dependencia de infraestructura**, no solo por prioridad: las Fases 1–7 se pueden hacer **sin servidor** (en el equipo del desarrollador y en GitHub); las Fases 8–14 **requieren la máquina de Azure** y su crédito. Dentro de cada fase, las tareas llevan la etiqueta de la historia de usuario de spec.md.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 HTTPS/acceso · US2 CI · US3 despliegue y vuelta atrás · US4 prueba de carga · US5 costo · US6 verificación manual y SC-007 del Módulo 1 · US7 respaldo
- **(manual)**: lo ejecuta el desarrollador en el portal de Azure, en GitHub o por SSH; Claude Code no crea recursos en Azure
- Include exact file paths in descriptions

## Path Conventions

Monolito Laravel 13 existente. Archivos nuevos: `.github/workflows/`, `docker/*/Dockerfile.prod`, `docker-compose.prod.yml`, `deploy/`, `docs/despliegue/`; evidencias en `specs/001-auth-roles-rbac/evidencias/`. Los comandos de aplicación en el servidor usan `docker compose -f docker-compose.prod.yml` desde `/opt/sge`.

---

## PARTE A — Sin servidor (equipo local y GitHub)

## Phase 1: Setup (preparación compartida)

- [x] T001 [P] Crear `.dockerignore` en la raíz excluyendo `.git`, `.env*` (salvo `.env.example`), `vendor`, `node_modules`, `storage/logs`, `storage/framework/{cache,sessions,views}`, `tests`, `specs`, `docs`, `.claude`, `.specify`, `.github` y `public/build`, para que ninguna imagen contenga secretos (FR-009, DC-003)
- [x] T002 [P] Parametrizar `docker/php/fpm-pool.conf` con `pm.max_children = ${FPM_MAX_CHILDREN}`, `pm.start_servers = ${FPM_START_SERVERS}`, `pm.min_spare_servers = ${FPM_MIN_SPARE}`, `pm.max_spare_servers = ${FPM_MAX_SPARE}` y definir en `docker/php/Dockerfile` (desarrollo) `ENV FPM_MAX_CHILDREN=20 FPM_START_SERVERS=6 FPM_MIN_SPARE=4 FPM_MAX_SPARE=10` para que el desarrollo local no cambie (research D-02)
- [x] T003 [P] Agregar a `.env.example` (comentadas, sin valores reales) las variables de despliegue y de perfil de tamaño de `contracts/variables-entorno.md` (`REGISTRO`, `FQDN`, `PERFIL_TAMANO`, `FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY`, `SESSION_SECURE_COOKIE`, `SGE_VERSION`) (constitución: `.env.example` actualizado)
- [x] T004 [P] Crear `deploy/env.staging.example` con todas las variables del `.env` del servidor de `contracts/variables-entorno.md` (APP_ENV=staging, APP_DEBUG=false, OPCACHE_VALIDATE_TIMESTAMPS=0, BCRYPT_ROUNDS=12, SESSION_SECURE_COOKIE=true, LOG_CHANNEL=stderr, DB_PASSWORD/DB_ROOT_PASSWORD/APP_KEY/SGE_ADMIN_PASSWORD con marcadores `<…>` y la instrucción de generarlos con `openssl rand -base64 24`) (FR-009, FR-010)
- [x] T005 Comprobar que el repositorio pasa Pint hoy (`docker compose exec app ./vendor/bin/pint --test`); si falla, aplicar `vendor/bin/pint` y confirmar el reformateo en un commit separado `style: formato Pint` para que el CI nazca en verde (research D-08)

---

## Phase 2: Foundational — imágenes de producción y cambios de aplicación (bloquea todo)

**⚠️ CRITICAL**: ni el CI de release, ni los scripts, ni nada del servidor se puede validar sin esta fase.

### Pruebas primero (deben fallar antes de implementar)

- [x] T006 [P] [US4] Escribir `tests/Feature/Seeders/LoadTestSeederEntornoTest.php`: el `LoadTestSeeder` se ejecuta en `local`, `staging` y `testing` y lanza `RuntimeException` en `production` y en cualquier otro nombre de entorno (p. ej. `qa`), sin crear usuarios cuando se rechaza (FR-020)
- [x] T007 [P] [US3] Escribir `tests/Feature/Salud/SaludTest.php` según `contracts/salud.md`: `GET /up` responde 200 `application/json` con `{"status":"ok","version":<config('app.version')>}`, `version` toma el valor de `SGE_VERSION` (default `dev`), no requiere autenticación, no envía cookie de sesión y las pantallas del Módulo 1 no cambian (FR-013, DC-004)

### Implementación

- [x] T008 [US4] Cambiar la guarda de `database/seeders/LoadTestSeeder.php` de "prohibido en producción" a lista permitida `['local','staging','testing']` con mensaje en español que nombra el entorno rechazado; hacer pasar T006 (FR-020)
- [x] T009 [US3] Agregar `'version' => env('SGE_VERSION', 'dev')` a `config/app.php`, crear `app/Http/Controllers/SaludController.php` (JSON `status` + `version`, dispara `Illuminate\Foundation\Events\DiagnosingHealth`) y en `bootstrap/app.php` quitar `health: '/up'` y registrar `Route::get('/up', SaludController::class)` mediante el parámetro `then:` de `withRouting` (fuera del grupo `web`: sin sesión ni Redis); **no tocar ninguna vista**; hacer pasar T007 (FR-013)
- [x] T010 [US3] Crear `docker/php/Dockerfile.prod` multi-stage (research D-03): etapa `assets` (`node:20-alpine`, `npm ci && npm run build`), etapa `vendor` (`composer:2`, `composer install --no-dev --classmap-authoritative --no-scripts --no-interaction`), etapa final `php:8.3-fpm` con las mismas extensiones del Dockerfile de desarrollo (`pdo_mysql bcmath intl zip pcntl opcache redis`), código copiado respetando `.dockerignore`, `vendor` y `public/build` de las etapas previas, `ENV OPCACHE_VALIDATE_TIMESTAMPS=0 FPM_MAX_CHILDREN=4 FPM_START_SERVERS=2 FPM_MIN_SPARE=1 FPM_MAX_SPARE=3`, `opcache.ini` y `fpm-pool.conf` copiados, usuario no root, sin git ni composer en la imagen final (FR-006, FR-007)
- [x] T011 [P] [US3] Crear `docker/php/entrypoint.prod.sh` (`php artisan optimize` y luego `exec php-fpm`; falla si falta `APP_KEY`) y referenciarlo como `ENTRYPOINT` en `docker/php/Dockerfile.prod` (constitución: `optimize` en cada despliegue)
- [x] T012 [P] [US1] Crear `docker/nginx/prod.conf`: servidor 80 con `location /.well-known/acme-challenge/` (webroot `/var/www/certbot`) y redirección 301 a `https://$host$request_uri` para lo demás; servidor 443 con `ssl_certificate /etc/letsencrypt/live/${FQDN}/fullchain.pem` (sustitución con `envsubst` en el arranque de la imagen), TLS 1.2/1.3, `fastcgi_param HTTPS on`, mismas cabeceras de seguridad que `docker/nginx/default.conf`, `Strict-Transport-Security` y `fastcgi_pass app:9000` (FR-001, FR-002)
- [x] T013 [US3] Crear `docker/nginx/Dockerfile.prod` (`nginx:alpine`, copia `public/` —incluido `public/build`— de la imagen `sge-app` de la misma versión mediante `--build-arg APP_IMAGE`, plantilla `prod.conf` en `/etc/nginx/templates/` para que `envsubst` inyecte `FQDN`) (depende de T010, T012; plan: dos imágenes)
- [x] T014 [US3] Crear `docker-compose.prod.yml`: servicios `web` (`${REGISTRO}/sge-web:${SGE_VERSION}`, puertos solo `80:80` y `443:443`, volúmenes de solo lectura `/etc/letsencrypt` y `/var/www/certbot`), `app` (`${REGISTRO}/sge-app:${SGE_VERSION}`, **sin `env_file`**: lista explícita `environment:` solo con las variables de aplicación y las credenciales de su usuario de base de datos `DB_*`; **sin `DB_ROOT_PASSWORD` ni `MYSQL_ROOT_PASSWORD`**; sin montar código), `db` (`mysql:8.0` con `MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}`, `MYSQL_USER`/`MYSQL_PASSWORD`/`MYSQL_DATABASE` desde `DB_*`, `--innodb-buffer-pool-size=${MYSQL_BUFFER_POOL}`, `--max-connections=${MYSQL_MAX_CONNECTIONS}`, `--performance-schema=OFF`, volumen `db_data`, **sin `ports:`**), `redis` (`--maxmemory ${REDIS_MAXMEMORY}`, **sin `ports:`**), `restart: unless-stopped`, healthchecks y rotación de logs `max-size 10m` / `max-file 3` (FR-006, FR-007, FR-008, FR-032, D-14)
- [x] T015 [US3] Validar localmente la pila de producción: construir `sge-app` y `sge-web` con `docker build`, levantar `docker-compose.prod.yml` con un `.env` temporal fuera del repositorio y un certificado autofirmado para `localhost`, y comprobar: `GET /up` = 200 por HTTPS con JSON que incluye `version`, redirección 80→443, `php -r 'echo ini_get("opcache.validate_timestamps");'` = `0`, `php artisan about` muestra entorno `staging` y cachés activas, `docker inspect` sin montajes de código, 3306 y 6379 no publicados, procesos como usuario no root, `docker run --rm <imagen> sh -c 'ls -a /var/www/html | grep -c "^\.env$"'` = 0 (imagen sin `.env`) y `docker compose -f docker-compose.prod.yml exec app env | grep -ci root` = 0 (la contraseña root de MySQL no llega a `app`) (DC-001 parcial, DC-002 parcial, DC-003, FR-032)

**Checkpoint**: imágenes de producción construibles y comprobadas en local; suite Pest verde (`docker compose exec app ./vendor/bin/pest`).

---

## Phase 3: User Story 2 — Integración continua visible en GitHub (Priority: P1)

**Goal**: cada push y pull request ejecuta Pest (MySQL + Redis) y Pint, con el resultado visible en GitHub.

**Independent Test**: un commit con prueba rota y otro con estilo roto salen rojos; uno correcto sale verde (quickstart §1).

- [x] T016 [US2] Crear `.github/workflows/ci.yml` según `contracts/workflows-github.md`: disparadores `push` (todas las ramas) y `pull_request`, `permissions: contents: read`, servicios `mysql:8.0` y `redis:7-alpine` con healthchecks, PHP 8.3 con extensiones `pdo_mysql, bcmath, intl, zip, redis` (`shivammathur/setup-php`), caché de Composer, `composer install`, Node 20 + `npm ci` + `npm run build`, `.env` desde `.env.example` + `key:generate`, creación de la BD `sge_testing`, job `pruebas` con `vendor/bin/pest --ci` y job `estilo` con `vendor/bin/pint --test` (FR-015, FR-016, FR-017)
- [x] T017 [US2] Resolver en `ci.yml` que `phpunit.xml` fija `DB_HOST=db`: añadir un paso con `sudo sh -c 'echo "127.0.0.1 db redis" >> /etc/hosts'` (opción (a) de research D-08, sin tocar `phpunit.xml`) y definir `REDIS_HOST=redis`; confirmar que la suite completa corre contra MySQL y Redis reales (FR-015)
- [x] T018 [P] [US2] Crear `docs/despliegue/integracion-continua.md` (en español): qué ejecuta el CI, cómo leer un fallo, cómo exigir los estados `ci / pruebas` y `ci / estilo` en la protección de la rama `main` (Settings → Branches), cómo correr Pest y Pint en local (FR-016, FR-030)
- [x] T019 [US2] (manual) Verificar el CI en GitHub con una rama de prueba: (1) push correcto → ambos jobs verdes; (2) test roto deliberado → `ci / pruebas` rojo con detalle; (3) archivo mal formateado → `ci / estilo` rojo; (4) abrir un PR y ver los estados; borrar la rama; anotar run IDs en `specs/002-despliegue-ci-staging/evidencias/ci.md` (DC-005)
- [x] T020 [US2] (manual) Activar en GitHub la protección de `main` exigiendo `ci / pruebas` y `ci / estilo`, y anotarlo en `specs/002-despliegue-ci-staging/evidencias/ci.md` (FR-016)

**Checkpoint**: US2 completa — el CI protege el resto del trabajo.

---

## Phase 4: User Story 3 (parte sin servidor) — Publicación de imágenes y scripts de despliegue (Priority: P1)

**Goal**: GitHub publica las imágenes por etiqueta y existen los scripts de despliegue/vuelta atrás listos para usarse.

**Independent Test**: etiquetar y ver las imágenes en GHCR; los scripts pasan `shellcheck` y su lógica se ensayó en local.

- [x] T021 [US3] Crear `.github/workflows/release.yml` según `contracts/workflows-github.md`: disparadores `push` de etiquetas `v*` y `workflow_dispatch` (entrada `etiqueta`), `permissions: contents: read, packages: write`, login a `ghcr.io` con `GITHUB_TOKEN`, Buildx `linux/amd64`, publica `ghcr.io/<propietario en minúsculas>/sge-app:<etiqueta>` (`docker/php/Dockerfile.prod`) y `sge-web:<etiqueta>` (`docker/nginx/Dockerfile.prod`) con caché GHA; resumen con *digests* y un recordatorio de verificar la visibilidad pública del paquete en la primera publicación; nunca publica en pull requests (FR-007, FR-006a)
- [x] T022 [P] [US3] Crear `deploy/lib.sh` con funciones comunes de los scripts: cargar y validar `/opt/sge/.env` (existe, permisos 0600, `APP_KEY` no vacío), aplicar el perfil `PERFIL_TAMANO` (`diario`/`carga`) exportando `FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY` con las tablas de `contracts/variables-entorno.md`, el comando `dc()` = `docker compose -f docker-compose.prod.yml`, `log()` y comprobación de `GET /up` (hasta 60 s)
- [x] T023 [US7] Crear `deploy/backup.sh [origen]` según `contracts/scripts-servidor.md`: `mysqldump --single-transaction --routines --triggers` dentro de `db` con la contraseña por `MYSQL_PWD` (no en la línea de comandos), `gzip`, destino `/var/backups/sge/sge-AAAAMMDD-HHMMSS-<origen>.sql.gz` (0600), verifica `gzip -t` y tamaño > 0, elimina archivos con más de 7 días (`find -mtime +7`) (FR-028)
- [x] T024 [US3] Crear `deploy/deploy.sh <etiqueta> | --reconfigurar | --estado` según `contracts/scripts-servidor.md` y research D-04: `git fetch --tags` + `git checkout <etiqueta>` y re-ejecución de sí mismo, verificación previa de que las imágenes de la etiqueta existen (`docker manifest inspect`), `backup.sh pre-<etiqueta>`, `pull`, `db`+`redis` arriba, `run --rm app php artisan migrate --force` **antes** de recrear `app`/`web` (si falla: sale ≠ 0, deja los contenedores anteriores y `SGE_VERSION` sin cambios e indica restaurar `pre-<etiqueta>` y desplegar la anterior), `up -d app web` con `SGE_VERSION=<etiqueta>` pasado por entorno, comprobación `GET /up` por HTTPS cuyo campo `version` debe ser igual a la etiqueta, y solo entonces escritura de `/opt/sge/VERSION` y de `SGE_VERSION` en `.env` y línea en `/var/log/sge/despliegues.log`; advierte si el tamaño de VM no coincide con `PERFIL_TAMANO` (FR-011, FR-012, FR-013, FR-014, FR-031)
- [x] T025 [P] [US3] Crear `deploy/bootstrap-servidor.sh <fqdn> <correo>` según `contracts/scripts-servidor.md` y research D-02/D-05/D-06/D-14: zona horaria `America/Lima`, swap 2 GB persistente con `vm.swappiness=10`, `unattended-upgrades`, Docker Engine + Compose v2 con rotación de logs, usuario `sge` (clave copiada de `azureuser`, grupo `docker`), SSH solo con clave y sin root, `ufw` (22/80/443), `fail2ban` (jail `sshd`: 5 fallos → 1 h), certbot (apt) con emisión `--standalone`, `certbot reconfigure` a webroot y gancho de recarga de nginx, clon del repositorio público en `/opt/sge`, directorios `/var/backups/sge` y `/var/log/sge`, instalación de `deploy/cron-sge`; idempotente y con resumen final (FR-002, FR-004, FR-005)
- [x] T026 [P] [US7] Crear `deploy/cron-sge` (`/etc/cron.d`): `backup.sh diario` a las 03:00 America/Lima como usuario `sge` (FR-028)
- [x] T027 [US7] Crear `deploy/restore.sh <archivo.sql.gz>` según `contracts/scripts-servidor.md`: confirmación escribiendo `RESTAURAR`, `artisan down`, detener `app`/`web`, recrear la base `sge`, cargar el volcado, `up -d`, `/up`, salir de mantenimiento e imprimir el conteo de usuarios (FR-029)
- [x] T028 [P] [US3] Crear `docs/despliegue/primer-despliegue.md` (en español): requisitos, `ssh`, ejecutar `bootstrap-servidor.sh`, crear `/opt/sge/.env` desde `deploy/env.staging.example` con `openssl rand`, `APP_KEY` generada con la imagen, sembrar el Administrador inicial, **verificar la visibilidad pública del paquete GHCR** (`docker logout ghcr.io; docker pull …`; si falla, Packages → Package settings → Change visibility → Public) y primer `deploy.sh` (FR-006a, FR-030)
- [x] T029 [P] [US3] Crear `docs/despliegue/desplegar-y-volver-atras.md` (en español): despliegue regular de una etiqueta (crear etiqueta → esperar `release` → `deploy.sh`), `--estado`, vuelta atrás desplegando la etiqueta anterior, límite de migraciones destructivas (usar `restore.sh` con el respaldo `pre-<etiqueta>`) y diagnóstico de fallos (FR-011, FR-012, FR-030)
- [x] T030 [P] [US1] Crear `docs/despliegue/certificado.md` (en español): emisión inicial, renovación automática, `certbot renew --dry-run`, qué hacer si falla el desafío (DNS no propagado, límites de Let's Encrypt) sin servir credenciales por HTTP (edge case de la spec) (FR-002)
- [x] T031 [P] [US7] Crear `docs/despliegue/respaldo-y-restauracion.md` (en español): respaldo automático diario y manual, retención de 7 días, restauración paso a paso, copia fuera del servidor con `scp` y su limitación (mismo disco) (FR-028, FR-029)
- [x] T032 [US3] Agregar un job `scripts` en `.github/workflows/ci.yml` que ejecute `shellcheck deploy/*.sh docker/php/entrypoint.prod.sh` (glob: cubre también `metrics.sh` y `registro-tamano.sh` cuando existan en la Fase 5) y `actionlint` sobre `.github/workflows/*.yml`; corregir los hallazgos de los scripts presentes (calidad de scripts y workflows)
- [x] T033 [US3] Ensayar en local la lógica de `deploy.sh`/`backup.sh`/`restore.sh` contra la pila de producción de T015 (rutas parametrizables por variable `SGE_DIR`/`BACKUP_DIR` para pruebas): respaldo → alterar datos → restauración devuelve el estado, y despliegue con una etiqueta inexistente falla sin tocar los contenedores en marcha

**Checkpoint**: todo el código de operación existe y está validado sin servidor.

---

## Phase 5: User Story 4 (parte sin servidor) — Workflow de carga y recolector de métricas (Priority: P2)

**Goal**: la prueba de 1000 VUs se lanza bajo demanda desde GitHub y existe el recolector y el cierre que prueba el regreso a B2ats_v2.

**Independent Test**: el workflow en modo humo contra cualquier URL `STAGING_URL` y `metrics.sh` en local generan los archivos esperados.

- [x] T034 [US4] Crear `.github/workflows/carga.yml` según `contracts/workflows-github.md`: solo `workflow_dispatch` con entradas `base_url` (default `vars.STAGING_URL`), `tamano_vm` (obligatoria), `version` (obligatoria), `humo` (boolean); guardas (falla si `base_url` ≠ `vars.STAGING_URL`, si falta la variable o si `GET /up` ≠ 200); ejecuta `grafana/k6` con `tests/Load/carga.js` (`-e BASE_URL`, `-e SMOKE=1` si `humo`), `--summary-export`; sube **siempre** (`if: always()`) el artefacto `evidencia-carga-<run_id>` con `k6-resumen.json`, `k6-salida.txt` y `meta.json` (URL, versión, tamaño declarado, SHA, run_id, fecha UTC, modalidad), retención 90 días; imprime el recordatorio de `metrics.sh start` (FR-018, FR-019, FR-020, FR-022)
- [x] T035 [US4] Crear `deploy/metrics.sh start|stop|tamano|cierre` según `contracts/scripts-servidor.md` y research D-12: `start` muestrea cada 5 s a `/var/log/sge/metricas-AAAAMMDD-HHMM.csv` (columnas del contrato; CPU de `/proc/stat`, memoria y swap de `/proc/meminfo`, contenedores de `docker stats --no-stream`) y escribe `meta-servidor.json` (incluye el modelo de CPU de `/proc/cpuinfo`, porque Dsv4 corre sobre distintos Intel); `stop` lo cierra e imprime máximos y p95; `tamano` lee `vmSize` del servicio de metadatos de Azure (`169.254.169.254`); `cierre` lee `/var/log/sge/tamano.log` y, solo si tras el último `stop` la última línea es `Standard_B2ats_v2`, escribe `cierre-carga.json` con `evidencia_completa: true` y sale 0; si no, imprime `EVIDENCIA INCOMPLETA` y sale 1 (FR-019, FR-022a)
- [x] T036 [P] [US4] Crear `deploy/registro-tamano.sh` y agregarlo a `deploy/cron-sge`: añade a `/var/log/sge/tamano.log` una línea `fecha-UTC vmSize` en `@reboot` y cada 5 minutos, para que el regreso a B2ats_v2 quede registrado en el servidor (FR-022a)
- [x] T037 [P] [US4] Crear `docs/despliegue/prueba-de-carga.md` (en español) con el procedimiento como **lista de verificación de 16 pasos** de `contracts/azure-portal.md` §7 — el último paso es "volver a B2ats_v2" y confirmarlo con `./deploy/metrics.sh cierre`; indicar explícitamente que **la evidencia solo se considera completa si `cierre-carga.json` dice `evidencia_completa: true`** y qué hacer si dice `EVIDENCIA INCOMPLETA`; **qué hacer si el servidor se cuelga durante la prueba (reiniciarlo desde el portal, conservar la evidencia parcial y volver igualmente a B2ats_v2 aunque la prueba haya fallado, según `contracts/azure-portal.md` §7)**; incluir cómo leer el artefacto, copiar la evidencia y registrar el costo de las horas en B2als_v2 (FR-021, FR-022, FR-022a)
- [x] T038 [P] [US4] Crear plantilla `specs/001-auth-roles-rbac/evidencias/k6-sesion-plantilla.md` con las 16 casillas de la lista de verificación, campos de hora de inicio/fin en B2als_v2, umbrales de SC-006 del Módulo 1 (DC-006) (login p95 < 3,5 s, consultas p95 < 1,5 s, fallos < 1 %), picos de CPU/memoria, enlace al artefacto, resultado del `cierre` y costo de la sesión (FR-019, FR-022a)
- [x] T039 [US4] Ensayar `metrics.sh start/stop/tamano/cierre` en local (con `tamano` simulado por variable de entorno `SGE_VMSIZE_FILE` y `tamano.log` temporal): verificar el CSV, `meta-servidor.json` y que `cierre` devuelve 1 si el último tamaño es B2als_v2 y 0 si es B2ats_v2; ejecutar `shellcheck deploy/*.sh` completo (incluye `metrics.sh` y `registro-tamano.sh`) y corregir hallazgos (FR-022a)

---

## Phase 6: User Stories 5 y 1 (documentación) — Costo, tamaño y aprovisionamiento (Priority: P1/P2)

**Goal**: la documentación operativa completa para que un tercero pueda aprovisionar y operar (DC-010).

- [x] T040 [P] [US5] Crear `docs/despliegue/costo-y-tamano.md` (en español): tabla de costo de `plan.md` con cómo actualizarla con los precios reales de la calculadora (disco e IP), alertas 50/75/90 %, procedimiento de cambio de tamaño en ambos sentidos con verificación y duración, detener/reactivar la VM (Stop con *deallocated*), riesgo de olvidar B2als_v2 (8,84 USD/semana) y recordatorios (FR-023, FR-024, FR-025)
- [x] T041 [P] [US1] Crear `docs/despliegue/aprovisionamiento-azure.md` (en español) a partir de `contracts/azure-portal.md` §0–§6: verificaciones previas (regiones, cuotas BASv2/BASv2, precios), grupo de recursos, VM, IP estática con DNS, NSG 22/80/443, clave SSH, alertas; con la tabla exacta de valores (FR-003, FR-004)
- [x] T042 [P] [US3] Crear `docs/despliegue/README.md` (índice en español de todos los procedimientos) y enlazarlo desde `README.md` raíz, reemplazando las URLs de ejemplo `https://staging.ejemplo.pe` en `README.md` y `specs/001-auth-roles-rbac/quickstart.md` por `https://<fqdn>` con un enlace a `docs/despliegue/` (FR-030)
- [x] T043 [P] [US6] Crear plantillas `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual-plantilla.md` (11 pasos de quickstart §3 con columnas fecha/versión/resultado/observaciones) y `staging-sc007-plantilla.md` (≥5 intentos, tiempo, peor caso, veredicto < 60 s) (FR-026, FR-027)

---

## Phase 7: Publicación de las imágenes y puerta previa al servidor

**Purpose**: dejar publicadas y comprobadas las dos versiones que se usarán para desplegar y volver atrás, antes de crear la VM.

- [x] T044 Integrar a `main` todo lo anterior mediante pull request con `ci` en verde y comprobar que la suite completa (`docker compose exec app ./vendor/bin/pest`) y `pint --test` pasan en local
- [x] T045 (manual) Etiquetar `v0.2.0` sobre `main` (`git tag v0.2.0 && git push origin v0.2.0`) y esperar `release` verde; anotar los *digests* de `sge-app` y `sge-web` (FR-007)
- [x] T046 (manual) **Verificar la visibilidad de GHCR tras la primera publicación**: desde un equipo sin sesión (`docker logout ghcr.io; docker pull ghcr.io/jorgeberroccal/sge-app:v0.2.0` y `…/sge-web:v0.2.0`); si responde `denied`/`unauthorized`, ir a GitHub → perfil → Packages → cada paquete → *Package settings* → *Change visibility* → **Public**, y repetir los dos pull anónimos hasta que funcionen; anotarlo en `specs/002-despliegue-ci-staging/evidencias/ghcr.md` (FR-006a)
- [x] T047 (manual) Comprobar en local que la imagen publicada `v0.2.0` arranca con la pila de producción de T015 (descarga anónima, `GET /up` informa `"version":"v0.2.0"` si el `.env` temporal define `SGE_VERSION=v0.2.0`)
- [x] T048 Crear un commit de documentación sin cambios de comportamiento (por ejemplo, fecha en `docs/despliegue/README.md`), integrarlo a `main` y etiquetar `v0.2.1`; esperar `release` verde. Esta etiqueta permite probar el despliegue y la vuelta atrás: la respuesta de `/up` pasará de `v0.2.0` a `v0.2.1` (DC-004, US3)
- [x] T049 (manual) Preparar la etiqueta de ensayo de migración fallida (FR-031): crear una rama desechable `prueba/migracion-falla` desde `main` con una migración `database/migrations/<fecha>_prueba_migracion_falla.php` que primero añada una columna a `usuarios` y luego lance una excepción (DDL parcial), **sin integrarla a `main`**; etiquetar `v0.2.9-prueba-falla`, esperar `release` verde y verificar la visibilidad pública de las imágenes como en T046; anotar en `specs/002-despliegue-ci-staging/evidencias/ghcr.md`. Después del ensayo (T069) borrar la etiqueta y la rama

**Checkpoint (fin de la Parte A)**: imágenes `v0.2.0` y `v0.2.1` públicas en GHCR, CI en verde, scripts y documentación listos. No se ha gastado crédito.

---

## PARTE B — Requiere el servidor de Azure (consume crédito)

## Phase 8: User Story 5 — Control de costo antes de crear recursos (Priority: P2)

**Purpose**: las alertas existen **antes** de que haya algo que facture.

- [x] T051 [US5] (manual) Ejecutar `docs/despliegue/aprovisionamiento-azure.md` §0: confirmar crédito y vencimiento, que **Chile Central** aparece entre las regiones permitidas, las cuotas (*Usage + quotas*) de BASv2 ≥ 2 y BASv2 ≥ 2 vCPU y regional ≥ 4; si falta cuota, solicitar el aumento; confirmar que B2ats_v2 → B2als_v2 aparece como cambio de tamaño válido; anotar en `specs/002-despliegue-ci-staging/evidencias/presupuestos.md` (plan D-01)
- [x] T052 [US5] (manual) Consultar en la calculadora de precios de Azure (Chile Central) el costo mensual del disco Standard SSD 30 GiB y de la IP pública Standard estática; actualizar la tabla de costo en `specs/002-despliegue-ci-staging/plan.md` y `docs/despliegue/costo-y-tamano.md` y confirmar que el total proyectado sigue ≤ 80 USD (DC-007)
- [x] T053 [US5] (manual) Crear el presupuesto `credito-estudiante` con alertas al 50 %, 75 % y 90 % (50/75/90 USD) por correo a `jorgeby2000@gmail.com` según `contracts/azure-portal.md` §6; además el presupuesto mensual `gasto-mensual` (25 USD, alertas al 80 % y 100 %) como alarma temprana; captura en `specs/002-despliegue-ci-staging/evidencias/Presupuestos.png` y explicación en `evidencias/presupuestos.md` (FR-023, DC-007)

---

## Phase 9: User Story 1 — Sistema accesible por HTTPS (Priority: P1) 🎯 MVP de la Parte B

**Goal**: el SGE responde en `https://<fqdn>` con certificado válido y puertos mínimos.

**Independent Test**: quickstart §2 pasos 6–10.

- [x] T054 [US1] (manual) Crear `rg-sge-staging` (Chile Central), la IP pública estática Standard `pip-sge-staging` con etiqueta DNS y el NSG con solo 22/80/443 según `contracts/azure-portal.md` §1, §3, §4; anotar el FQDN exacto (FR-001, FR-004)
- [x] T055 [US1] (manual) Crear `vm-sge-staging`: Ubuntu 24.04 LTS x64 Gen2, **Standard_B2ats_v2**, disco **Standard SSD** 30 GiB, clave SSH (clave pública existente `~/.ssh/id_ed25519.pub`; sin `.pem`), sin puertos públicos adicionales, según `contracts/azure-portal.md` §2 (FR-003) — hecho 2026-10-20 con otros nombres (`sge-staging`, FQDN `sge-peru.chilecentral.cloudapp.azure.com`), ver `evidencias/vm-azure.md`
- [x] T056 [US1] Conectar por SSH y ejecutar `deploy/bootstrap-servidor.sh <fqdn> <correo>`; comprobar el resumen (swap 2 GB activo con `swapon --show`, `ufw status`, `fail2ban-client status sshd`, SSH sin contraseña, certificado emitido y su expiración) (FR-002, FR-005)
- [x] T057 [US1] Crear `/opt/sge/.env` (0600) desde `deploy/env.staging.example` con secretos generados en el servidor (`openssl rand -base64 24`, `APP_KEY` generada con la imagen publicada), `APP_URL=https://<fqdn>`, `FQDN`, `REGISTRO`, `PERFIL_TAMANO=diario`; confirmar que nada de esto está en el repositorio (FR-009, FR-010)
- [x] T058 [US3] Ejecutar `./deploy/deploy.sh v0.2.0` en el servidor; sembrar el Administrador inicial con `php artisan db:seed --force` (DatabaseSeeder: roles, permisos y Administrador inicial; los usuarios de demostración solo se crean en `local`); cronometrar el despliegue (< 15 min, DC-004) (FR-011)
- [x] T059 [US1] Validar en el servidor y desde fuera: candado válido sin advertencias, `http`→`https`, inicio de sesión del Administrador y `curl -s https://<fqdn>/up` con `"version":"v0.2.0"`; escaneo externo (`nmap -Pn -p 22,80,443,3306,6379 <fqdn>`) con solo 22/80/443; `certbot renew --dry-run` correcto; `opcache.validate_timestamps=0`; `.env` con permisos 600; `exec app env | grep -ci root` = 0; búsqueda de secretos en el repositorio sin resultados; registrar en `specs/002-despliegue-ci-staging/evidencias/https-y-red.md` (DC-001, DC-002, DC-003, FR-032)
- [x] T060 [US1] Probar con `free -m` y `docker stats` en reposo que la pila cabe en 1 GB con swap (uso de swap, memoria disponible); ajustar `PERFIL_TAMANO=diario` si fuera necesario y anotar los valores finales en `contracts/variables-entorno.md` (research D-02)
- [ ] T061 [US4] (manual) Verificar con `gh variable list` que la variable de repositorio `STAGING_URL=https://<fqdn>` existe (definida el 20/10/2026) (antes, comprobar que `carga.yml` en modo humo falla con mensaje claro sin la variable: edge case "secretos no configurados"; absorbe la antigua T050) y ejecutar `carga.yml` con `humo=true` (10 VUs) tras sembrar `LoadTestSeeder`; comprobar el artefacto y el funcionamiento del login desde internet (FR-018)

**Checkpoint**: US1 completa. El sistema está en internet con HTTPS.

---

## Phase 10: User Story 3 (validación en servidor) — Despliegue repetible y vuelta atrás (Priority: P1)

- [x] T062 [US3] Desplegar `v0.2.1` con `./deploy/deploy.sh v0.2.1`: `/up` pasa a informar `v0.2.1`, los usuarios siguen existiendo y hay un respaldo `pre-v0.2.1` (< 15 min) (FR-011, FR-013, FR-014)
- [x] T063 [US3] Volver a la anterior con `./deploy/deploy.sh v0.2.0`: `/up` vuelve a informar `v0.2.0`, datos intactos, `--estado` y `despliegues.log` reflejan ambos pasos (< 10 min, DC-004) (FR-012)
- [x] T064 [US3] Probar fallo controlado: `./deploy/deploy.sh v9.9.9` (etiqueta inexistente) termina con error claro y sin cambiar los contenedores en marcha; reiniciar la VM desde el portal y comprobar que la pila vuelve sola y la versión activa se mantiene (FR-008)
- [x] T065 [US3] Registrar tiempos y resultados en `specs/002-despliegue-ci-staging/evidencias/despliegue-y-vuelta-atras.md` (DC-004)

---

## Phase 11: User Story 7 — Respaldo y restauración en el servidor (Priority: P3)

- [x] T066 [US7] Comprobar `deploy/cron-sge` instalado (`ls /etc/cron.d`), ejecutar `./deploy/backup.sh manual` y verificar archivo, permisos 0600 y `gzip -t`; confirmar al día siguiente que existe el respaldo `diario` de las 03:00 (FR-028)
- [x] T067 [US7] Probar la restauración: crear un usuario de prueba, ejecutar `./deploy/restore.sh <archivo>`, verificar que el usuario ya no existe y que el Administrador inicia sesión; cronometrar (< 15 min, DC-009); registrar en `specs/002-despliegue-ci-staging/evidencias/respaldo-restauracion.md` (FR-029)
- [x] T068 [US7] Probar la retención: crear un archivo de respaldo de prueba con fecha de 8 días atrás (`touch -d`) y comprobar que el siguiente `backup.sh` lo elimina; copiar un respaldo al equipo con `scp` y anotar el procedimiento (FR-028)
- [x] T069 [US3] (manual) **Ensayo de recuperación ante migración fallida (FR-031, DC-012)** — combina restauración y fallo provocado: (1) `./deploy/backup.sh manual` y anotar la hora de inicio; (2) `./deploy/deploy.sh v0.2.9-prueba-falla` debe terminar con error en la migración, `/up` debe seguir informando la versión anterior y `SGE_VERSION`/`VERSION` no cambian; (3) comprobar el estado parcial de la base (columna añadida por la migración defectuosa); (4) recuperarse con el procedimiento documentado: `./deploy/restore.sh <pre-v0.2.9-prueba-falla>` y luego `./deploy/deploy.sh v0.2.0`; (5) verificar que el Administrador inicia sesión, que los datos coinciden con el respaldo y que la columna defectuosa ya no existe; (6) registrar el tiempo total (< 25 min) y cada paso en `specs/002-despliegue-ci-staging/evidencias/migracion-fallida.md`; (7) borrar la etiqueta `v0.2.9-prueba-falla` y la rama de ensayo (FR-031, FR-029, DC-012)

---

## Phase 12: User Story 6 — Verificación manual y SC-007 del Módulo 1 en el servidor (Priority: P2)

- [x] T070 [US6] (manual) Ejecutar los 11 pasos de `specs/001-auth-roles-rbac/quickstart.md` §3 contra `https://<fqdn>` desde un navegador real, registrando cada paso en `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual.md` (basado en la plantilla T043) con fecha, versión desplegada y observaciones (FR-026, DC-008)
- [x] T071 [US6] (manual) Medir SC-007 del Módulo 1 en el servidor con **al menos 5 intentos** de crear una cuenta con rol, anotando el tiempo de cada uno y el peor caso en `specs/001-auth-roles-rbac/evidencias/staging-sc007.md`; declarar cumplido solo si el peor caso es < 60 s (FR-027, DC-008)

---

## Phase 13: User Story 4 — Prueba de carga de 1000 VUs con evidencia (Priority: P2)

**Goal**: cerrar SC-006 del Módulo 1. Cada sesión sigue la lista de verificación de 16 pasos de `docs/despliegue/prueba-de-carga.md`; **la evidencia solo está completa si `./deploy/metrics.sh cierre` devuelve `evidencia_completa: true`** (FR-022a).

**Independent Test**: quickstart §6.

- [x] T071a [US4] (manual) **Prueba de ida y vuelta B2ats_v2 ↔ B2als_v2 con el servidor vacío** (antes de poner datos o certificado; research D-01c): `az vm list-vm-resize-options` muestra B2als_v2 → Stop (*deallocated*) → Size B2als_v2 → Start → SSH, `free -m` ≈ 4 GB, «CPU Credits Remaining» (¿con cuántos créditos arranca?) y que la métrica aparezca en *Metrics* → Stop → Size B2ats_v2 → Start → SSH; registrar tiempos y créditos en `evidencias/vm-azure.md`
- [x] T071b [US4] Reemplazar D2s_v4 por B2als_v2 como tamaño de carga en spec, plan, research (D-01c: por qué se descartaron D2s_v5, D2s_v4 y DS2_v2), costos, contratos, scripts (`metrics.sh`, `deploy.sh`, `lib.sh` perfil `carga` de 4 GB, `env.staging.example`), `carga.yml`, `docs/despliegue/` y plantilla de sesión (lista de 16 pasos con control de créditos)
- [x] T071c [US4] Crear `deploy/creditos-cpu.sh` (PC, `az monitor metrics list`): CSV por minuto y `creditos-resumen.json` con créditos al iniciar, mínimo, ¿agotados? y minuto de la rampa en que la CPU quedó limitada; ensayado con una respuesta sintética (`SGE_AZ_JSON`) — `evidencias/creditos-cpu-local.md` (FR-019, FR-022)
- [ ] T072 [US4] (manual) Preparación (casillas 1–2 de la lista): confirmar cuota BASv2 y holgura de crédito ≥ 5 USD, `./deploy/backup.sh manual`, y duplicar la plantilla T038 como `specs/001-auth-roles-rbac/evidencias/k6-sesion-<fecha>.md` (FR-021)
- [ ] T073 [US4] (manual) Cambio a B2als_v2 (casillas 3–7): Stop (*deallocated*) → Size B2als_v2 → Start; `PERFIL_TAMANO=carga` y `./deploy/deploy.sh --reconfigurar`; `/up` = 200 y `metrics.sh tamano` = `Standard_B2als_v2`; anotar la hora de inicio de facturación (FR-025)
- [ ] T074 [US4] (manual) Siembra, humo y créditos de partida (casillas 8–10): `LoadTestSeeder` en staging, workflow `carga` con `humo=true` correcto y «CPU Credits Remaining» ≥ 200 (esperar con la VM encendida y sin carga si hace falta; anotar valor y hora) (FR-020)
- [ ] T075 [US4] (manual) **Rampa completa, intento 1** (casillas 11–14): `metrics.sh start` (anotar la hora UTC) → workflow `carga` (`humo=false`, `tamano_vm=Standard_B2als_v2`, `version=v0.2.0` o la desplegada) → `metrics.sh stop`; descargar el artefacto de GitHub y `metricas-*.csv` + `meta-servidor.json` por `scp`; en el PC, `./deploy/creditos-cpu.sh <desde-utc> <fin-utc> <inicio-rampa-utc>` y anotar si los créditos se agotaron y desde qué minuto la CPU quedó limitada (si se agotan, la prueba se registra igual); copiarlos a `specs/001-auth-roles-rbac/evidencias/` con la fecha y resumir umbrales y picos de CPU/memoria en el registro de la sesión; guardar el resultado aunque falle (FR-018, FR-019, FR-022)
- [ ] T076 [US4] (manual) Si los umbrales no se cumplen o el servidor se cuelga (reiniciar desde el portal, `metrics.sh stop`, conservar el CSV parcial y pasar directo al regreso a B2ats_v2 de T077): registrar el análisis del cuello de botella con los picos de CPU/memoria, ajustar el perfil `carga` (FPM/MySQL/Redis) en `contracts/variables-entorno.md` sin cambiar el costo de bcrypt, y repetir el intento 2 y, si hace falta, el 3 (dentro de la misma sesión de 3–4 h o en otra) (FR-022)
- [ ] T077 [US4] (manual) **Regreso al tamaño económico (casillas 15–16, último paso de la sesión)**: `PERFIL_TAMANO=diario` + `deploy.sh --reconfigurar`; Stop (*deallocated*) → Size **B2ats_v2** → Start; `/up` = 200; ejecutar `./deploy/metrics.sh cierre`: debe imprimir `evidencia_completa: true`; si imprime `EVIDENCIA INCOMPLETA`, repetir el regreso antes de cualquier otra cosa (FR-022a, FR-025)
- [ ] T078 [US4] (manual) Copiar `cierre-carga.json` junto a la evidencia, marcar las 16 casillas del registro de la sesión, anotar horas totales en B2als_v2 y su costo (horas × 0,0526 USD) y comprobar en *Cost analysis* que no hay cargos nuevos de B2als_v2 tras el regreso; **commit** de la evidencia solo si `evidencia_completa: true` (FR-019, FR-022a)
- [ ] T079 [US4] (manual) Repetir T072–T078 hasta tener **2 sesiones** documentadas: una completa y una de repetición para comprobar que los resultados se reproducen (el plan calcula 2 sesiones de 28 h = 56 h en B2als_v2); cada una con su `cierre-carga.json` verdadero (SC-006 del Módulo 1 (DC-006))
- [ ] T080 [US4] Si alguna ejecución con evidencia completa cumple los umbrales de SC-006 del Módulo 1 (DC-006) (login p95 < 3,5 s, consultas p95 < 1,5 s, fallos < 1 %): actualizar `specs/001-auth-roles-rbac/spec.md` (estado de SC-006 del Módulo 1 (DC-006) con la referencia a la evidencia), `specs/001-auth-roles-rbac/tasks.md` y `specs/001-auth-roles-rbac/quickstart.md` §4, y registrar el cierre del criterio; si ninguna cumple, documentar el incumplimiento y el análisis sin declararlo cumplido (FR-022, SC-006 del Módulo 1 (DC-006))

---

## Phase 14: Polish & cross-cutting

- [ ] T081 [P] Ejecutar `/speckit-analyze` sobre el feature ya implementado y resolver hallazgos; actualizar `research.md` con las desviaciones reales (valores finales del perfil `carga`, precios reales, tiempos medidos)
- [ ] T082 [P] Revisar `docs/despliegue/` completo siguiendo solo la documentación desde cero (lectura crítica o con un tercero) y corregir lagunas (DC-010)
- [ ] T083 (manual) Revisión de costo final: *Cost analysis* al 15/11 y 15/12 contra la estimación, y confirmar que el proyectado al 31/12 ≤ 80 % del crédito (DC-007); decidir si se detiene la VM (*Stop* deallocated) hasta la sustentación y documentarlo
- [ ] T084 Actualizar `README.md` con la URL pública de staging, el estado de CI y enlaces a `docs/despliegue/` y a las evidencias; cerrar con una etiqueta final (p. ej. `v0.2.2`) si se cambió algo desplegable y dejar el servidor en la versión final con `deploy.sh`
- [ ] T085 Revisión final de seguridad: `git log -p` y búsqueda de secretos en el repositorio y en las imágenes (`docker history`, `docker run --rm <imagen> env`), SSH solo con clave, puertos y permisos de `.env`, y registro en `specs/002-despliegue-ci-staging/evidencias/https-y-red.md` (DC-003)

---

## Dependencies & Execution Order

### Fases

- **Parte A (sin servidor)**: Fase 1 → Fase 2 (bloquea) → Fases 3, 4, 5, 6 (3 y 4 pueden ir en paralelo tras Fase 2; 5 y 6 dependen de scripts de la Fase 4 solo para T035–T036 y T039) → Fase 7 (publicación; requiere 1–6 integrados y CI verde).
- **Parte B (servidor)**: Fase 8 (costo) → Fase 9 (VM + HTTPS + primer despliegue) → Fases 10, 11, 12 (independientes entre sí, tras la Fase 9) → Fase 13 (carga; requiere 9 y 11 (respaldo previo) y de preferencia 12 ya hecha para no gastar horas de B2als_v2 con la VM) → Fase 14.
- La Fase 7 (T045–T048) precede a T058 porque el servidor descarga las imágenes publicadas.

### Dependencias dentro de fases

- T006/T007 antes de T008/T009. T010 → T011 → T013 → T014 → T015. T016 → T017 → T019 → T020.
- T022 antes de T023/T024/T027/T035. T024 usa T023. T036 antes de T035 `cierre` en pruebas (T039).
- T045 → T046 → T047; T048 después de T046. T061 después de T059.
- T073 → T074 → T075 → T076 → T077 → T078. **T077 es obligatorio al final de cada sesión.**
- T049 (etiqueta de ensayo con migración defectuosa) antes de T069; T069 requiere T067 (restauración probada) y T062–T063 (despliegue y vuelta atrás); T069 termina borrando la etiqueta de ensayo.
- T007 antes de T009; T024 depende de T009 (el campo `version` de `/up` es la verificación del despliegue).

### Oportunidades de paralelismo

- Fase 1: T001–T004 en paralelo.
- Fase 2: T006 y T007 en paralelo; T011 y T012 en paralelo.
- Fase 4: T022, T025, T026, T028–T031 en paralelo (archivos distintos).
- Fases 5 y 6: T036–T038 y T040–T043 en paralelo.
- Fase 3 (CI) en paralelo con la Fase 4 por personas/sesiones distintas.

## Implementation Strategy

- **MVP de la Parte A**: Fases 1–3 (imágenes y CI) — ya protege el repositorio.
- **MVP de la Parte B**: Fases 8–9 (alertas, VM, HTTPS, primer despliegue) — el sistema queda en internet.
- **Entrega incremental**: A completa → publicar `v0.2.0`/`v0.2.1` → Parte B por fases; la prueba de carga (Fase 13) va al final para consumir B2als_v2 solo cuando todo lo demás funciona.
- **Regla de oro de costo**: ninguna sesión de B2als_v2 se da por terminada sin `metrics.sh cierre` → `evidencia_completa: true`.

## Cobertura de requisitos

Los criterios de éxito de este feature se identifican como **DC-xxx** para no confundirlos con los SC-xxx del Módulo 1.

| Requisito | Tareas |
|---|---|
| FR-001, FR-002 | T012, T025, T030, T054, T056, T059 |
| FR-003, FR-004, FR-005 | T025, T041, T054, T055, T056 |
| FR-006, FR-006a | T010, T011, T014, T021, T028, T046, T049 |
| FR-007, FR-008 | T001, T010, T013, T014, T015, T064 |
| FR-009, FR-010 | T001, T003, T004, T057 |
| FR-011–FR-014 | T007, T009, T022, T024, T029, T058, T062–T064 |
| FR-015–FR-017 | T016–T020 |
| FR-018–FR-022a | T006, T008, T034, T035, T036, T037, T038, T061, T072–T080 |
| FR-023–FR-025 | T040, T051–T053, T073, T077, T083 |
| FR-026, FR-027 | T043, T070, T071 |
| FR-028, FR-029 | T023, T026, T027, T031, T066–T069 |
| FR-030 | T018, T028–T031, T037, T040–T042 |
| FR-031 | T024, T049, T069 |
| FR-032 | T014, T015, T059 |
| DC-001–DC-003 | T015, T059, T085 |
| DC-004 | T007, T058, T062, T063, T065 |
| DC-005 | T019 |
| DC-006 (cierra SC-006 del Módulo 1) | T075–T080 |
| DC-007 | T052, T053, T083 |
| DC-008 (verifica SC-007 del Módulo 1) | T070, T071 |
| DC-009 | T067 |
| DC-010 | T082 |
| DC-011 | T035, T036, T037, T077, T078 |
| DC-012 | T049, T069 |
