---

description: "Task list for 002-despliegue-ci-staging (Despliegue en staging e integración continua)"
---

# Tasks: Despliegue en servidor staging e integración continua

**Input**: Design documents from `/specs/002-despliegue-ci-staging/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: El único código de aplicación que cambia (guarda del `LoadTestSeeder`, versión visible en el pie de la página de acceso) lleva pruebas Pest escritas primero (Principio V). Scripts, Dockerfiles y workflows se validan con `shellcheck`, `actionlint` y una ejecución local de la pila de producción (Fase 2); lo que depende del servidor se valida con [quickstart.md](quickstart.md).

**Organization**: Las fases están ordenadas por **dependencia de infraestructura**, no solo por prioridad: las Fases 1–8 se pueden hacer **sin servidor** (en el equipo del desarrollador y en GitHub); las Fases 9–15 **requieren la máquina de Azure** y su crédito. Dentro de cada fase, las tareas llevan la etiqueta de la historia de usuario de spec.md.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 HTTPS/acceso · US2 CI · US3 despliegue y vuelta atrás · US4 prueba de carga · US5 costo · US6 verificación manual y SC-007 · US7 respaldo
- **(manual)**: lo ejecuta el desarrollador en el portal de Azure, en GitHub o por SSH; Claude Code no crea recursos en Azure
- Include exact file paths in descriptions

## Path Conventions

Monolito Laravel 13 existente. Archivos nuevos: `.github/workflows/`, `docker/*/Dockerfile.prod`, `docker-compose.prod.yml`, `deploy/`, `docs/despliegue/`; evidencias en `specs/001-auth-roles-rbac/evidencias/`. Los comandos de aplicación en el servidor usan `docker compose -f docker-compose.prod.yml` desde `/opt/sge`.

---

## PARTE A — Sin servidor (equipo local y GitHub)

## Phase 1: Setup (preparación compartida)

- [ ] T001 [P] Crear `.dockerignore` en la raíz excluyendo `.git`, `.env*` (salvo `.env.example`), `vendor`, `node_modules`, `storage/logs`, `storage/framework/{cache,sessions,views}`, `tests`, `specs`, `docs`, `.claude`, `.specify`, `.github` y `public/build`, para que ninguna imagen contenga secretos (FR-009, SC-003)
- [ ] T002 [P] Parametrizar `docker/php/fpm-pool.conf` con `pm.max_children = ${FPM_MAX_CHILDREN}`, `pm.start_servers = ${FPM_START_SERVERS}`, `pm.min_spare_servers = ${FPM_MIN_SPARE}`, `pm.max_spare_servers = ${FPM_MAX_SPARE}` y definir en `docker/php/Dockerfile` (desarrollo) `ENV FPM_MAX_CHILDREN=20 FPM_START_SERVERS=6 FPM_MIN_SPARE=4 FPM_MAX_SPARE=10` para que el desarrollo local no cambie (research D-02)
- [ ] T003 [P] Agregar a `.env.example` (comentadas, sin valores reales) las variables de despliegue y de perfil de tamaño de `contracts/variables-entorno.md` (`REGISTRO`, `FQDN`, `PERFIL_TAMANO`, `FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY`, `SESSION_SECURE_COOKIE`, `SGE_VERSION`) (constitución: `.env.example` actualizado)
- [ ] T004 [P] Crear `deploy/env.staging.example` con todas las variables del `.env` del servidor de `contracts/variables-entorno.md` (APP_ENV=staging, APP_DEBUG=false, OPCACHE_VALIDATE_TIMESTAMPS=0, BCRYPT_ROUNDS=12, SESSION_SECURE_COOKIE=true, LOG_CHANNEL=stderr, DB_PASSWORD/DB_ROOT_PASSWORD/APP_KEY/SGE_ADMIN_PASSWORD con marcadores `<…>` y la instrucción de generarlos con `openssl rand -base64 24`) (FR-009, FR-010)
- [ ] T005 Comprobar que el repositorio pasa Pint hoy (`docker compose exec app ./vendor/bin/pint --test`); si falla, aplicar `vendor/bin/pint` y confirmar el reformateo en un commit separado `style: formato Pint` para que el CI nazca en verde (research D-08)

---

## Phase 2: Foundational — imágenes de producción y cambios de aplicación (bloquea todo)

**⚠️ CRITICAL**: ni el CI de release, ni los scripts, ni nada del servidor se puede validar sin esta fase.

### Pruebas primero (deben fallar antes de implementar)

- [ ] T006 [P] [US4] Escribir `tests/Feature/Seeders/LoadTestSeederEntornoTest.php`: el `LoadTestSeeder` se ejecuta en `local`, `staging` y `testing` y lanza `RuntimeException` en `production` y en cualquier otro nombre de entorno (p. ej. `qa`), sin crear usuarios cuando se rechaza (FR-020)
- [ ] T007 [P] [US1] Escribir `tests/Feature/Auth/VersionVisibleTest.php`: la página `/login` muestra el texto `Versión <valor de config('app.version')>` en el pie, y el valor sale de `SGE_VERSION` con default `dev` (permite comprobar a simple vista qué versión corre al desplegar y volver atrás, SC-004)

### Implementación

- [ ] T008 [US4] Cambiar la guarda de `database/seeders/LoadTestSeeder.php` de "prohibido en producción" a lista permitida `['local','staging','testing']` con mensaje en español que nombra el entorno rechazado; hacer pasar T006 (FR-020)
- [ ] T009 [US1] Agregar `'version' => env('SGE_VERSION', 'dev')` a `config/app.php` y mostrar `Versión {{ config('app.version') }}` en el pie de `resources/views/layouts/guest.blade.php`; hacer pasar T007
- [ ] T010 [US3] Crear `docker/php/Dockerfile.prod` multi-stage (research D-03): etapa `assets` (`node:20-alpine`, `npm ci && npm run build`), etapa `vendor` (`composer:2`, `composer install --no-dev --classmap-authoritative --no-scripts --no-interaction`), etapa final `php:8.3-fpm` con las mismas extensiones del Dockerfile de desarrollo (`pdo_mysql bcmath intl zip pcntl opcache redis`), código copiado respetando `.dockerignore`, `vendor` y `public/build` de las etapas previas, `ENV OPCACHE_VALIDATE_TIMESTAMPS=0 FPM_MAX_CHILDREN=4 FPM_START_SERVERS=2 FPM_MIN_SPARE=1 FPM_MAX_SPARE=3`, `opcache.ini` y `fpm-pool.conf` copiados, usuario no root, sin git ni composer en la imagen final (FR-006, FR-007)
- [ ] T011 [P] [US3] Crear `docker/php/entrypoint.prod.sh` (`php artisan optimize` y luego `exec php-fpm`; falla si falta `APP_KEY`) y referenciarlo como `ENTRYPOINT` en `docker/php/Dockerfile.prod` (constitución: `optimize` en cada despliegue)
- [ ] T012 [P] [US1] Crear `docker/nginx/prod.conf`: servidor 80 con `location /.well-known/acme-challenge/` (webroot `/var/www/certbot`) y redirección 301 a `https://$host$request_uri` para lo demás; servidor 443 con `ssl_certificate /etc/letsencrypt/live/${FQDN}/fullchain.pem` (sustitución con `envsubst` en el arranque de la imagen), TLS 1.2/1.3, `fastcgi_param HTTPS on`, mismas cabeceras de seguridad que `docker/nginx/default.conf`, `Strict-Transport-Security` y `fastcgi_pass app:9000` (FR-001, FR-002)
- [ ] T013 [US3] Crear `docker/nginx/Dockerfile.prod` (`nginx:alpine`, copia `public/` de la etapa `assets`/código de `Dockerfile.prod`, plantilla `prod.conf` en `/etc/nginx/templates/` para que `envsubst` inyecte `FQDN`) (depende de T010, T012; plan: dos imágenes)
- [ ] T014 [US3] Crear `docker-compose.prod.yml`: servicios `web` (`${REGISTRO}/sge-web:${SGE_VERSION}`, puertos solo `80:80` y `443:443`, volúmenes de solo lectura `/etc/letsencrypt` y `/var/www/certbot`), `app` (`${REGISTRO}/sge-app:${SGE_VERSION}`, `env_file: .env`, sin montar código), `db` (`mysql:8.0` con `--innodb-buffer-pool-size=${MYSQL_BUFFER_POOL}`, `--max-connections=${MYSQL_MAX_CONNECTIONS}`, `--performance-schema=OFF`, volumen `db_data`, **sin `ports:`**), `redis` (`--maxmemory ${REDIS_MAXMEMORY}`, **sin `ports:`**), `restart: unless-stopped`, healthchecks y rotación de logs `max-size 10m` / `max-file 3` (FR-006, FR-007, FR-008, D-14)
- [ ] T015 [US3] Validar localmente la pila de producción: construir `sge-app` y `sge-web` con `docker build`, levantar `docker-compose.prod.yml` con un `.env` temporal fuera del repositorio y un certificado autofirmado para `localhost`, y comprobar: `GET /up` = 200 por HTTPS, redirección 80→443, `php -r 'echo ini_get("opcache.validate_timestamps");'` = `0`, `php artisan about` muestra entorno `staging` y cachés activas, `docker inspect` sin montajes de código, 3306 y 6379 no publicados, procesos como usuario no root, y `docker run --rm <imagen> sh -c 'ls -a /var/www/html | grep -c "^\.env$"'` = 0 (imagen sin `.env`) (SC-001 parcial, SC-002 parcial, SC-003)

**Checkpoint**: imágenes de producción construibles y comprobadas en local; suite Pest verde (`docker compose exec app ./vendor/bin/pest`).

---

## Phase 3: User Story 2 — Integración continua visible en GitHub (Priority: P1)

**Goal**: cada push y pull request ejecuta Pest (MySQL + Redis) y Pint, con el resultado visible en GitHub.

**Independent Test**: un commit con prueba rota y otro con estilo roto salen rojos; uno correcto sale verde (quickstart §1).

- [ ] T016 [US2] Crear `.github/workflows/ci.yml` según `contracts/workflows-github.md`: disparadores `push` (todas las ramas) y `pull_request`, `permissions: contents: read`, servicios `mysql:8.0` y `redis:7-alpine` con healthchecks, PHP 8.3 con extensiones `pdo_mysql, bcmath, intl, zip, redis` (`shivammathur/setup-php`), caché de Composer, `composer install`, Node 20 + `npm ci` + `npm run build`, `.env` desde `.env.example` + `key:generate`, creación de la BD `sge_testing`, job `pruebas` con `vendor/bin/pest --ci` y job `estilo` con `vendor/bin/pint --test` (FR-015, FR-016, FR-017)
- [ ] T017 [US2] Resolver en `ci.yml` que `phpunit.xml` fija `DB_HOST=db`: añadir un paso con `sudo sh -c 'echo "127.0.0.1 db redis" >> /etc/hosts'` (opción (a) de research D-08, sin tocar `phpunit.xml`) y definir `REDIS_HOST=redis`; confirmar que la suite completa corre contra MySQL y Redis reales (FR-015)
- [ ] T018 [P] [US2] Crear `docs/despliegue/integracion-continua.md` (en español): qué ejecuta el CI, cómo leer un fallo, cómo exigir los estados `ci / pruebas` y `ci / estilo` en la protección de la rama `main` (Settings → Branches), cómo correr Pest y Pint en local (FR-016, FR-030)
- [ ] T019 [US2] (manual) Verificar el CI en GitHub con una rama de prueba: (1) push correcto → ambos jobs verdes; (2) test roto deliberado → `ci / pruebas` rojo con detalle; (3) archivo mal formateado → `ci / estilo` rojo; (4) abrir un PR y ver los estados; borrar la rama; anotar run IDs en `specs/002-despliegue-ci-staging/evidencias/ci.md` (SC-005)
- [ ] T020 [US2] (manual) Activar en GitHub la protección de `main` exigiendo `ci / pruebas` y `ci / estilo`, y anotarlo en `specs/002-despliegue-ci-staging/evidencias/ci.md` (FR-016)

**Checkpoint**: US2 completa — el CI protege el resto del trabajo.

---

## Phase 4: User Story 3 (parte sin servidor) — Publicación de imágenes y scripts de despliegue (Priority: P1)

**Goal**: GitHub publica las imágenes por etiqueta y existen los scripts de despliegue/vuelta atrás listos para usarse.

**Independent Test**: etiquetar y ver las imágenes en GHCR; los scripts pasan `shellcheck` y su lógica se ensayó en local.

- [ ] T021 [US3] Crear `.github/workflows/release.yml` según `contracts/workflows-github.md`: disparadores `push` de etiquetas `v*` y `workflow_dispatch` (entrada `etiqueta`), `permissions: contents: read, packages: write`, login a `ghcr.io` con `GITHUB_TOKEN`, Buildx `linux/amd64`, publica `ghcr.io/<propietario en minúsculas>/sge-app:<etiqueta>` (`docker/php/Dockerfile.prod`) y `sge-web:<etiqueta>` (`docker/nginx/Dockerfile.prod`) con caché GHA; resumen con *digests* y un recordatorio de verificar la visibilidad pública del paquete en la primera publicación; nunca publica en pull requests (FR-007, FR-006a)
- [ ] T022 [P] [US3] Crear `deploy/lib.sh` con funciones comunes de los scripts: cargar y validar `/opt/sge/.env` (existe, permisos 0600, `APP_KEY` no vacío), aplicar el perfil `PERFIL_TAMANO` (`diario`/`carga`) exportando `FPM_*`, `MYSQL_*`, `REDIS_MAXMEMORY` con las tablas de `contracts/variables-entorno.md`, el comando `dc()` = `docker compose -f docker-compose.prod.yml`, `log()` y comprobación de `GET /up` (hasta 60 s)
- [ ] T023 [US7] Crear `deploy/backup.sh [origen]` según `contracts/scripts-servidor.md`: `mysqldump --single-transaction --routines --triggers` dentro de `db` con la contraseña por `MYSQL_PWD` (no en la línea de comandos), `gzip`, destino `/var/backups/sge/sge-AAAAMMDD-HHMMSS-<origen>.sql.gz` (0600), verifica `gzip -t` y tamaño > 0, elimina archivos con más de 7 días (`find -mtime +7`) (FR-028)
- [ ] T024 [US3] Crear `deploy/deploy.sh <etiqueta> | --reconfigurar | --estado` según `contracts/scripts-servidor.md` y research D-04: `git fetch --tags` + `git checkout <etiqueta>` y re-ejecución de sí mismo, verificación previa de que las imágenes de la etiqueta existen (`docker manifest inspect`), `backup.sh pre-<etiqueta>`, `pull`, `db`+`redis` arriba, `run --rm app php artisan migrate --force`, `up -d app web`, `/up` por HTTPS, escritura de `/opt/sge/VERSION` y de `SGE_VERSION` en `.env`, línea en `/var/log/sge/despliegues.log`; en fallo imprime el comando de vuelta atrás y el respaldo previo; advierte si el tamaño de VM no coincide con `PERFIL_TAMANO` (FR-011, FR-012, FR-013, FR-014)
- [ ] T025 [P] [US3] Crear `deploy/bootstrap-servidor.sh <fqdn> <correo>` según `contracts/scripts-servidor.md` y research D-02/D-05/D-06/D-14: zona horaria `America/Lima`, swap 2 GB persistente con `vm.swappiness=10`, `unattended-upgrades`, Docker Engine + Compose v2 con rotación de logs, usuario `sge` (clave copiada de `azureuser`, grupo `docker`), SSH solo con clave y sin root, `ufw` (22/80/443), `fail2ban` (jail `sshd`: 5 fallos → 1 h), certbot (apt) con emisión `--standalone`, `certbot reconfigure` a webroot y gancho de recarga de nginx, clon del repositorio público en `/opt/sge`, directorios `/var/backups/sge` y `/var/log/sge`, instalación de `deploy/cron-sge`; idempotente y con resumen final (FR-002, FR-004, FR-005)
- [ ] T026 [P] [US7] Crear `deploy/cron-sge` (`/etc/cron.d`): `backup.sh diario` a las 03:00 America/Lima como usuario `sge` (FR-028)
- [ ] T027 [US7] Crear `deploy/restore.sh <archivo.sql.gz>` según `contracts/scripts-servidor.md`: confirmación escribiendo `RESTAURAR`, `artisan down`, detener `app`/`web`, recrear la base `sge`, cargar el volcado, `up -d`, `/up`, salir de mantenimiento e imprimir el conteo de usuarios (FR-029)
- [ ] T028 [P] [US3] Crear `docs/despliegue/primer-despliegue.md` (en español): requisitos, `ssh`, ejecutar `bootstrap-servidor.sh`, crear `/opt/sge/.env` desde `deploy/env.staging.example` con `openssl rand`, `APP_KEY` generada con la imagen, sembrar el Administrador inicial, **verificar la visibilidad pública del paquete GHCR** (`docker logout ghcr.io; docker pull …`; si falla, Packages → Package settings → Change visibility → Public) y primer `deploy.sh` (FR-006a, FR-030)
- [ ] T029 [P] [US3] Crear `docs/despliegue/desplegar-y-volver-atras.md` (en español): despliegue regular de una etiqueta (crear etiqueta → esperar `release` → `deploy.sh`), `--estado`, vuelta atrás desplegando la etiqueta anterior, límite de migraciones destructivas (usar `restore.sh` con el respaldo `pre-<etiqueta>`) y diagnóstico de fallos (FR-011, FR-012, FR-030)
- [ ] T030 [P] [US1] Crear `docs/despliegue/certificado.md` (en español): emisión inicial, renovación automática, `certbot renew --dry-run`, qué hacer si falla el desafío (DNS no propagado, límites de Let's Encrypt) sin servir credenciales por HTTP (edge case de la spec) (FR-002)
- [ ] T031 [P] [US7] Crear `docs/despliegue/respaldo-y-restauracion.md` (en español): respaldo automático diario y manual, retención de 7 días, restauración paso a paso, copia fuera del servidor con `scp` y su limitación (mismo disco) (FR-028, FR-029)
- [ ] T032 [US3] Agregar al job de CI (o a un job `scripts` en `.github/workflows/ci.yml`) la verificación de `shellcheck deploy/*.sh docker/php/entrypoint.prod.sh` y `actionlint` sobre `.github/workflows/*.yml`; corregir los hallazgos (calidad de scripts y workflows)
- [ ] T033 [US3] Ensayar en local la lógica de `deploy.sh`/`backup.sh`/`restore.sh` contra la pila de producción de T015 (rutas parametrizables por variable `SGE_DIR`/`BACKUP_DIR` para pruebas): respaldo → alterar datos → restauración devuelve el estado, y despliegue con una etiqueta inexistente falla sin tocar los contenedores en marcha

**Checkpoint**: todo el código de operación existe y está validado sin servidor.

---

## Phase 5: User Story 4 (parte sin servidor) — Workflow de carga y recolector de métricas (Priority: P2)

**Goal**: la prueba de 1000 VUs se lanza bajo demanda desde GitHub y existe el recolector y el cierre que prueba el regreso a B2ats_v2.

**Independent Test**: el workflow en modo humo contra cualquier URL `STAGING_URL` y `metrics.sh` en local generan los archivos esperados.

- [ ] T034 [US4] Crear `.github/workflows/carga.yml` según `contracts/workflows-github.md`: solo `workflow_dispatch` con entradas `base_url` (default `vars.STAGING_URL`), `tamano_vm` (obligatoria), `version` (obligatoria), `humo` (boolean); guardas (falla si `base_url` ≠ `vars.STAGING_URL`, si falta la variable o si `GET /up` ≠ 200); ejecuta `grafana/k6` con `tests/Load/carga.js` (`-e BASE_URL`, `-e SMOKE=1` si `humo`), `--summary-export`; sube **siempre** (`if: always()`) el artefacto `evidencia-carga-<run_id>` con `k6-resumen.json`, `k6-salida.txt` y `meta.json` (URL, versión, tamaño declarado, SHA, run_id, fecha UTC, modalidad), retención 90 días; imprime el recordatorio de `metrics.sh start` (FR-018, FR-019, FR-020, FR-022)
- [ ] T035 [US4] Crear `deploy/metrics.sh start|stop|tamano|cierre` según `contracts/scripts-servidor.md` y research D-12: `start` muestrea cada 5 s a `/var/log/sge/metricas-AAAAMMDD-HHMM.csv` (columnas del contrato; CPU de `/proc/stat`, memoria y swap de `/proc/meminfo`, contenedores de `docker stats --no-stream`) y escribe `meta-servidor.json` (incluye el modelo de CPU de `/proc/cpuinfo`, porque Dsv4 corre sobre distintos Intel); `stop` lo cierra e imprime máximos y p95; `tamano` lee `vmSize` del servicio de metadatos de Azure (`169.254.169.254`); `cierre` lee `/var/log/sge/tamano.log` y, solo si tras el último `stop` la última línea es `Standard_B2ats_v2`, escribe `cierre-carga.json` con `evidencia_completa: true` y sale 0; si no, imprime `EVIDENCIA INCOMPLETA` y sale 1 (FR-019, FR-022a)
- [ ] T036 [P] [US4] Crear `deploy/registro-tamano.sh` y agregarlo a `deploy/cron-sge`: añade a `/var/log/sge/tamano.log` una línea `fecha-UTC vmSize` en `@reboot` y cada 5 minutos, para que el regreso a B2ats_v2 quede registrado en el servidor (FR-022a)
- [ ] T037 [P] [US4] Crear `docs/despliegue/prueba-de-carga.md` (en español) con el procedimiento como **lista de verificación de 14 pasos** de `contracts/azure-portal.md` §7 — el último paso es "volver a B2ats_v2" y confirmarlo con `./deploy/metrics.sh cierre`; indicar explícitamente que **la evidencia solo se considera completa si `cierre-carga.json` dice `evidencia_completa: true`** y qué hacer si dice `EVIDENCIA INCOMPLETA`; incluir cómo leer el artefacto, copiar la evidencia y registrar el costo de las horas en D2s_v4 (FR-021, FR-022, FR-022a)
- [ ] T038 [P] [US4] Crear plantilla `specs/001-auth-roles-rbac/evidencias/k6-sesion-plantilla.md` con las 14 casillas de la lista de verificación, campos de hora de inicio/fin en D2s_v4, umbrales de SC-006 (login p95 < 3,5 s, consultas p95 < 1,5 s, fallos < 1 %), picos de CPU/memoria, enlace al artefacto, resultado del `cierre` y costo de la sesión (FR-019, FR-022a)
- [ ] T039 [US4] Ensayar `metrics.sh start/stop/tamano/cierre` en local (con `tamano` simulado por variable de entorno `SGE_VMSIZE_FILE` y `tamano.log` temporal): verificar el CSV, `meta-servidor.json` y que `cierre` devuelve 1 si el último tamaño es D2s_v4 y 0 si es B2ats_v2 (FR-022a)

---

## Phase 6: User Stories 5 y 1 (documentación) — Costo, tamaño y aprovisionamiento (Priority: P1/P2)

**Goal**: la documentación operativa completa para que un tercero pueda aprovisionar y operar (SC-010).

- [ ] T040 [P] [US5] Crear `docs/despliegue/costo-y-tamano.md` (en español): tabla de costo de `plan.md` con cómo actualizarla con los precios reales de la calculadora (disco e IP), alertas 50/75/90 %, procedimiento de cambio de tamaño en ambos sentidos con verificación y duración, detener/reactivar la VM (Stop con *deallocated*), riesgo de olvidar D2s_v4 (22,54 USD/semana) y recordatorios (FR-023, FR-024, FR-025)
- [ ] T041 [P] [US1] Crear `docs/despliegue/aprovisionamiento-azure.md` (en español) a partir de `contracts/azure-portal.md` §0–§6: verificaciones previas (regiones, cuotas BASv2/DSv4, precios), grupo de recursos, VM, IP estática con DNS, NSG 22/80/443, clave SSH, alertas; con la tabla exacta de valores (FR-003, FR-004)
- [ ] T042 [P] [US3] Crear `docs/despliegue/README.md` (índice en español de todos los procedimientos) y enlazarlo desde `README.md` raíz, reemplazando las URLs de ejemplo `https://staging.ejemplo.pe` en `README.md` y `specs/001-auth-roles-rbac/quickstart.md` por `https://<fqdn>` con un enlace a `docs/despliegue/` (FR-030)
- [ ] T043 [P] [US6] Crear plantillas `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual-plantilla.md` (11 pasos de quickstart §3 con columnas fecha/versión/resultado/observaciones) y `staging-sc007-plantilla.md` (≥5 intentos, tiempo, peor caso, veredicto < 60 s) (FR-026, FR-027)

---

## Phase 7: Publicación de las imágenes y puerta previa al servidor

**Purpose**: dejar publicadas y comprobadas las dos versiones que se usarán para desplegar y volver atrás, antes de crear la VM.

- [ ] T044 Integrar a `main` todo lo anterior mediante pull request con `ci` en verde y comprobar que la suite completa (`docker compose exec app ./vendor/bin/pest`) y `pint --test` pasan en local
- [ ] T045 (manual) Etiquetar `v0.2.0` sobre `main` (`git tag v0.2.0 && git push origin v0.2.0`) y esperar `release` verde; anotar los *digests* de `sge-app` y `sge-web` (FR-007)
- [ ] T046 (manual) **Verificar la visibilidad de GHCR tras la primera publicación**: desde un equipo sin sesión (`docker logout ghcr.io; docker pull ghcr.io/jorgeberroccal/sge-app:v0.2.0` y `…/sge-web:v0.2.0`); si responde `denied`/`unauthorized`, ir a GitHub → perfil → Packages → cada paquete → *Package settings* → *Change visibility* → **Public**, y repetir los dos pull anónimos hasta que funcionen; anotarlo en `specs/002-despliegue-ci-staging/evidencias/ghcr.md` (FR-006a)
- [ ] T047 (manual) Comprobar en local que la imagen publicada `v0.2.0` arranca con la pila de producción de T015 (descarga anónima, `/up`, versión `0.2.0` visible en el pie del login)
- [ ] T048 Crear un commit de documentación sin cambios de comportamiento (por ejemplo, fecha en `docs/despliegue/README.md`), integrarlo a `main` y etiquetar `v0.2.1`; esperar `release` verde. Esta etiqueta permite probar el despliegue y la vuelta atrás con una diferencia visible: el pie del login mostrará la versión (SC-004, US3)
- [ ] T049 (manual) Definir en GitHub la variable de repositorio `STAGING_URL` con la URL del servidor **solo cuando el FQDN exista** (tarea T060) y comprobar que `carga.yml` en modo humo falla con mensaje claro mientras no exista (edge case "secretos no configurados")

**Checkpoint (fin de la Parte A)**: imágenes `v0.2.0` y `v0.2.1` públicas en GHCR, CI en verde, scripts y documentación listos. No se ha gastado crédito.

---

## PARTE B — Requiere el servidor de Azure (consume crédito)

## Phase 8: User Story 5 — Control de costo antes de crear recursos (Priority: P2)

**Purpose**: las alertas existen **antes** de que haya algo que facture.

- [ ] T050 [US5] (manual) Ejecutar `docs/despliegue/aprovisionamiento-azure.md` §0: confirmar crédito y vencimiento, que **Chile Central** aparece entre las regiones permitidas, las cuotas (*Usage + quotas*) de BASv2 ≥ 2 y DSv4 ≥ 2 vCPU y regional ≥ 4; si falta cuota, solicitar el aumento; confirmar que B2ats_v2 → D2s_v4 aparece como cambio de tamaño válido; anotar en `specs/002-despliegue-ci-staging/evidencias/azure-previo.md` (plan D-01)
- [ ] T051 [US5] (manual) Consultar en la calculadora de precios de Azure (Chile Central) el costo mensual del disco Standard SSD 30 GiB y de la IP pública Standard estática; actualizar la tabla de costo en `specs/002-despliegue-ci-staging/plan.md` y `docs/despliegue/costo-y-tamano.md` y confirmar que el total proyectado sigue ≤ 80 USD (SC-007)
- [ ] T052 [US5] (manual) Crear el presupuesto `credito-estudiante` con alertas al 50 %, 75 % y 90 % (50/75/90 USD) por correo a `jorgeby2000@gmail.com` según `contracts/azure-portal.md` §6; guardar captura de la lista de alertas en `specs/002-despliegue-ci-staging/evidencias/presupuesto-alertas.png` (FR-023, SC-007)

---

## Phase 9: User Story 1 — Sistema accesible por HTTPS (Priority: P1) 🎯 MVP de la Parte B

**Goal**: el SGE responde en `https://<fqdn>` con certificado válido y puertos mínimos.

**Independent Test**: quickstart §2 pasos 6–10.

- [ ] T053 [US1] (manual) Crear `rg-sge-staging` (Chile Central), la IP pública estática Standard `pip-sge-staging` con etiqueta DNS y el NSG con solo 22/80/443 según `contracts/azure-portal.md` §1, §3, §4; anotar el FQDN exacto (FR-001, FR-004)
- [ ] T054 [US1] (manual) Crear `vm-sge-staging`: Ubuntu 24.04 LTS x64 Gen2, **Standard_B2ats_v2**, disco **Standard SSD** 30 GiB, clave SSH (guardar el `.pem` fuera del repositorio con `chmod 600`), sin puertos públicos adicionales, según `contracts/azure-portal.md` §2 (FR-003)
- [ ] T055 [US1] Conectar por SSH y ejecutar `deploy/bootstrap-servidor.sh <fqdn> <correo>`; comprobar el resumen (swap 2 GB activo con `swapon --show`, `ufw status`, `fail2ban-client status sshd`, SSH sin contraseña, certificado emitido y su expiración) (FR-002, FR-005)
- [ ] T056 [US1] Crear `/opt/sge/.env` (0600) desde `deploy/env.staging.example` con secretos generados en el servidor (`openssl rand -base64 24`, `APP_KEY` generada con la imagen publicada), `APP_URL=https://<fqdn>`, `FQDN`, `REGISTRO`, `PERFIL_TAMANO=diario`; confirmar que nada de esto está en el repositorio (FR-009, FR-010)
- [ ] T057 [US3] Ejecutar `./deploy/deploy.sh v0.2.0` en el servidor; sembrar el Administrador inicial con `db:seed --class=AdministradorInicialSeeder --force`; cronometrar el despliegue (< 15 min, SC-004) (FR-011)
- [ ] T058 [US1] Validar en el servidor y desde fuera: candado válido sin advertencias, `http`→`https`, inicio de sesión del Administrador y pie `Versión v0.2.0`; escaneo externo (`nmap -Pn -p 22,80,443,3306,6379 <fqdn>`) con solo 22/80/443; `certbot renew --dry-run` correcto; `opcache.validate_timestamps=0`; `.env` con permisos 600; búsqueda de secretos en el repositorio sin resultados; registrar en `specs/002-despliegue-ci-staging/evidencias/https-y-red.md` (SC-001, SC-002, SC-003)
- [ ] T059 [US1] Probar con `free -m` y `docker stats` en reposo que la pila cabe en 1 GB con swap (uso de swap, memoria disponible); ajustar `PERFIL_TAMANO=diario` si fuera necesario y anotar los valores finales en `contracts/variables-entorno.md` (research D-02)
- [ ] T060 [US4] (manual) Definir la variable de repositorio `STAGING_URL=https://<fqdn>` en GitHub y ejecutar `carga.yml` con `humo=true` (10 VUs) tras sembrar `LoadTestSeeder`; comprobar el artefacto y el funcionamiento del login desde internet (FR-018)

**Checkpoint**: US1 completa. El sistema está en internet con HTTPS.

---

## Phase 10: User Story 3 (validación en servidor) — Despliegue repetible y vuelta atrás (Priority: P1)

- [ ] T061 [US3] Desplegar `v0.2.1` con `./deploy/deploy.sh v0.2.1`: el pie del login pasa a `Versión v0.2.1`, los usuarios siguen existiendo y hay un respaldo `pre-v0.2.1` (< 15 min) (FR-011, FR-013, FR-014)
- [ ] T062 [US3] Volver a la anterior con `./deploy/deploy.sh v0.2.0`: el pie vuelve a `Versión v0.2.0`, datos intactos, `--estado` y `despliegues.log` reflejan ambos pasos (< 10 min, SC-004) (FR-012)
- [ ] T063 [US3] Probar fallo controlado: `./deploy/deploy.sh v9.9.9` (etiqueta inexistente) termina con error claro y sin cambiar los contenedores en marcha; reiniciar la VM desde el portal y comprobar que la pila vuelve sola y la versión activa se mantiene (FR-008)
- [ ] T064 [US3] Registrar tiempos y resultados en `specs/002-despliegue-ci-staging/evidencias/despliegue-y-vuelta-atras.md` (SC-004)

---

## Phase 11: User Story 7 — Respaldo y restauración en el servidor (Priority: P3)

- [ ] T065 [US7] Comprobar `deploy/cron-sge` instalado (`ls /etc/cron.d`), ejecutar `./deploy/backup.sh manual` y verificar archivo, permisos 0600 y `gzip -t`; confirmar al día siguiente que existe el respaldo `diario` de las 03:00 (FR-028)
- [ ] T066 [US7] Probar la restauración: crear un usuario de prueba, ejecutar `./deploy/restore.sh <archivo>`, verificar que el usuario ya no existe y que el Administrador inicia sesión; cronometrar (< 15 min, SC-009); registrar en `specs/002-despliegue-ci-staging/evidencias/respaldo-restauracion.md` (FR-029)
- [ ] T067 [US7] Probar la retención: crear un archivo de respaldo de prueba con fecha de 8 días atrás (`touch -d`) y comprobar que el siguiente `backup.sh` lo elimina; copiar un respaldo al equipo con `scp` y anotar el procedimiento (FR-028)

---

## Phase 12: User Story 6 — Verificación manual y SC-007 en el servidor (Priority: P2)

- [ ] T068 [US6] (manual) Ejecutar los 11 pasos de `specs/001-auth-roles-rbac/quickstart.md` §3 contra `https://<fqdn>` desde un navegador real, registrando cada paso en `specs/001-auth-roles-rbac/evidencias/staging-verificacion-manual.md` (basado en la plantilla T043) con fecha, versión desplegada y observaciones (FR-026, SC-008)
- [ ] T069 [US6] (manual) Medir SC-007 en el servidor con **al menos 5 intentos** de crear una cuenta con rol, anotando el tiempo de cada uno y el peor caso en `specs/001-auth-roles-rbac/evidencias/staging-sc007.md`; declarar cumplido solo si el peor caso es < 60 s (FR-027, SC-008)

---

## Phase 13: User Story 4 — Prueba de carga de 1000 VUs con evidencia (Priority: P2)

**Goal**: cerrar SC-006 del Módulo 1. Cada sesión sigue la lista de verificación de 14 pasos de `docs/despliegue/prueba-de-carga.md`; **la evidencia solo está completa si `./deploy/metrics.sh cierre` devuelve `evidencia_completa: true`** (FR-022a).

**Independent Test**: quickstart §6.

- [ ] T070 [US4] (manual) Preparación (casillas 1–3 de la lista): confirmar cuota DSv4 y holgura de crédito ≥ 5 USD, `./deploy/backup.sh manual`, y duplicar la plantilla T038 como `specs/001-auth-roles-rbac/evidencias/k6-sesion-<fecha>.md` (FR-021)
- [ ] T071 [US4] (manual) Cambio a D2s_v4 (casillas 4–7): Stop (*deallocated*) → Size D2s_v4 → Start; `PERFIL_TAMANO=carga` y `./deploy/deploy.sh --reconfigurar`; `/up` = 200 y `metrics.sh tamano` = `Standard_D2s_v4`; anotar la hora de inicio de facturación (FR-025)
- [ ] T072 [US4] (manual) Siembra y humo (casillas 8–9): `LoadTestSeeder` en staging y workflow `carga` con `humo=true` correcto (FR-020)
- [ ] T073 [US4] (manual) **Rampa completa, intento 1** (casillas 10–12): `metrics.sh start` → workflow `carga` (`humo=false`, `tamano_vm=Standard_D2s_v4`, `version=v0.2.0` o la desplegada) → `metrics.sh stop`; descargar el artefacto de GitHub y `metricas-*.csv` + `meta-servidor.json` por `scp`; copiarlos a `specs/001-auth-roles-rbac/evidencias/` con la fecha y resumir umbrales y picos de CPU/memoria en el registro de la sesión; guardar el resultado aunque falle (FR-018, FR-019, FR-022)
- [ ] T074 [US4] (manual) Si los umbrales no se cumplen: registrar el análisis del cuello de botella con los picos de CPU/memoria, ajustar el perfil `carga` (FPM/MySQL/Redis) en `contracts/variables-entorno.md` sin cambiar el costo de bcrypt, y repetir el intento 2 y, si hace falta, el 3 (dentro de la misma sesión de 3–4 h o en otra) (FR-022)
- [ ] T075 [US4] (manual) **Regreso al tamaño económico (casillas 13–14, último paso de la sesión)**: `PERFIL_TAMANO=diario` + `deploy.sh --reconfigurar`; Stop (*deallocated*) → Size **B2ats_v2** → Start; `/up` = 200; ejecutar `./deploy/metrics.sh cierre`: debe imprimir `evidencia_completa: true`; si imprime `EVIDENCIA INCOMPLETA`, repetir el regreso antes de cualquier otra cosa (FR-022a, FR-025)
- [ ] T076 [US4] (manual) Copiar `cierre-carga.json` junto a la evidencia, marcar las 14 casillas del registro de la sesión, anotar horas totales en D2s_v4 y su costo (horas × 0,1341 USD) y comprobar en *Cost analysis* que no hay cargos nuevos de D2s_v4 tras el regreso; **commit** de la evidencia solo si `evidencia_completa: true` (FR-019, FR-022a)
- [ ] T077 [US4] (manual) Repetir T070–T076 hasta tener **al menos tres sesiones/ejecuciones** documentadas (la spec 002 plan calcula 3 sesiones de 4 h + 1 de holgura); cada una con su `cierre-carga.json` verdadero (SC-006)
- [ ] T078 [US4] Si alguna ejecución con evidencia completa cumple los umbrales de SC-006 (login p95 < 3,5 s, consultas p95 < 1,5 s, fallos < 1 %): actualizar `specs/001-auth-roles-rbac/spec.md` (estado de SC-006 con la referencia a la evidencia), `specs/001-auth-roles-rbac/tasks.md` y `specs/001-auth-roles-rbac/quickstart.md` §4, y registrar el cierre del criterio; si ninguna cumple, documentar el incumplimiento y el análisis sin declararlo cumplido (FR-022, SC-006)

---

## Phase 14: Polish & cross-cutting

- [ ] T079 [P] Ejecutar `/speckit-analyze` sobre el feature ya implementado y resolver hallazgos; actualizar `research.md` con las desviaciones reales (valores finales del perfil `carga`, precios reales, tiempos medidos)
- [ ] T080 [P] Revisar `docs/despliegue/` completo siguiendo solo la documentación desde cero (lectura crítica o con un tercero) y corregir lagunas (SC-010)
- [ ] T081 (manual) Revisión de costo final: *Cost analysis* al 15/11 y 15/12 contra la estimación, y confirmar que el proyectado al 31/12 ≤ 80 % del crédito (SC-007); decidir si se detiene la VM (*Stop* deallocated) hasta la sustentación y documentarlo
- [ ] T082 Actualizar `README.md` con la URL pública de staging, el estado de CI y enlaces a `docs/despliegue/` y a las evidencias; cerrar con una etiqueta final (p. ej. `v0.2.2`) si se cambió algo desplegable y dejar el servidor en la versión final con `deploy.sh`
- [ ] T083 Revisión final de seguridad: `git log -p` y búsqueda de secretos en el repositorio y en las imágenes (`docker history`, `docker run --rm <imagen> env`), SSH solo con clave, puertos y permisos de `.env`, y registro en `specs/002-despliegue-ci-staging/evidencias/https-y-red.md` (SC-003)

---

## Dependencies & Execution Order

### Fases

- **Parte A (sin servidor)**: Fase 1 → Fase 2 (bloquea) → Fases 3, 4, 5, 6 (3 y 4 pueden ir en paralelo tras Fase 2; 5 y 6 dependen de scripts de la Fase 4 solo para T035–T036 y T039) → Fase 7 (publicación; requiere 1–6 integrados y CI verde).
- **Parte B (servidor)**: Fase 8 (costo) → Fase 9 (VM + HTTPS + primer despliegue) → Fases 10, 11, 12 (independientes entre sí, tras la Fase 9) → Fase 13 (carga; requiere 9 y 11 (respaldo previo) y de preferencia 12 ya hecha para no gastar horas de D2s_v4 con la VM) → Fase 14.
- La Fase 7 (T045–T048) precede a T057 porque el servidor descarga las imágenes publicadas.

### Dependencias dentro de fases

- T006/T007 antes de T008/T009. T010 → T011 → T013 → T014 → T015. T016 → T017 → T019 → T020.
- T022 antes de T023/T024/T027/T035. T024 usa T023. T036 antes de T035 `cierre` en pruebas (T039).
- T045 → T046 → T047; T048 después de T046. T060 después de T058.
- T071 → T072 → T073 → T074 → T075 → T076. **T075 es obligatorio al final de cada sesión.**

### Oportunidades de paralelismo

- Fase 1: T001–T004 en paralelo.
- Fase 2: T006 y T007 en paralelo; T011 y T012 en paralelo.
- Fase 4: T022, T025, T026, T028–T031 en paralelo (archivos distintos).
- Fases 5 y 6: T036–T038 y T040–T043 en paralelo.
- Fase 3 (CI) en paralelo con la Fase 4 por personas/sesiones distintas.

## Implementation Strategy

- **MVP de la Parte A**: Fases 1–3 (imágenes y CI) — ya protege el repositorio.
- **MVP de la Parte B**: Fases 8–9 (alertas, VM, HTTPS, primer despliegue) — el sistema queda en internet.
- **Entrega incremental**: A completa → publicar `v0.2.0`/`v0.2.1` → Parte B por fases; la prueba de carga (Fase 13) va al final para consumir D2s_v4 solo cuando todo lo demás funciona.
- **Regla de oro de costo**: ninguna sesión de D2s_v4 se da por terminada sin `metrics.sh cierre` → `evidencia_completa: true`.

## Cobertura de requisitos

| Requisito | Tareas |
|---|---|
| FR-001, FR-002 | T012, T025, T030, T053, T055, T058 |
| FR-003, FR-004, FR-005 | T025, T041, T053, T054, T055 |
| FR-006, FR-006a | T010, T011, T014, T021, T028, T046 |
| FR-007, FR-008 | T001, T010, T013, T014, T015, T063 |
| FR-009, FR-010 | T001, T003, T004, T056 |
| FR-011–FR-014 | T022, T024, T029, T057, T061–T063 |
| FR-015–FR-017 | T016–T020 |
| FR-018–FR-022a | T006, T008, T034, T035, T036, T037, T038, T060, T070–T078 |
| FR-023–FR-025 | T040, T050–T052, T071, T075, T081 |
| FR-026, FR-027 | T043, T068, T069 |
| FR-028, FR-029 | T023, T026, T027, T031, T065–T067 |
| FR-030 | T018, T028–T031, T037, T040–T042 |
| SC-001–SC-003 | T015, T058, T083 |
| SC-004 | T057, T061, T062, T064 |
| SC-005 | T019 |
| SC-006 | T073–T078 |
| SC-007 | T051, T052, T081 |
| SC-008 | T068, T069 |
| SC-009 | T066 |
| SC-010 | T080 |
