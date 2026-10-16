# Implementation Plan: Despliegue en servidor staging e integración continua

**Branch**: `002-despliegue-ci-staging` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-despliegue-ci-staging/spec.md`

**Módulo MVP**: Transversal — infraestructura que sirve a los seis módulos, aprobada explícitamente
por el desarrollador según el Principio IV. Cierra el SC-006 del Módulo 1 como uno de sus objetivos.

## Summary

Poner el SGE en una VM Ubuntu 24.04 x86 de Azure for Students (región **Chile Central**), como
staging con HTTPS (Let's Encrypt sobre `*.cloudapp.azure.com`), sin que el servidor construya nada:
GitHub Actions construye dos imágenes de producción (`sge-app`: PHP-FPM con el código y `vendor`
sin dev, `sge-web`: nginx con `public/` incluido) y las publica en GHCR etiquetadas por versión. El
desarrollador despliega por SSH con `deploy/deploy.sh <etiqueta>`, que respalda la BD, descarga las
imágenes, migra y verifica; volver atrás es desplegar la etiqueta anterior. Otro workflow ejecuta
Pest (MySQL + Redis reales) y Pint en cada push y pull request, y un tercero lanza bajo demanda la
rampa de 1000 VUs de k6 contra la URL pública. El día a día corre en **B2ats_v2** (1 GB, 9,64
USD/mes) con swap de 2 GB y servicios dimensionados por variables de entorno; para la prueba se
cambia la VM a **D2s_v4** (2 vCPU, 8 GB, 97,92 USD/mes) solo por horas. Un script en el servidor
registra CPU/memoria/tamaño durante la prueba. Respaldo diario con retención de 7 días y restauración
documentada. Costo estimado hasta el 31/12/2026: **≈45 USD (45 %)**, 54 USD con 20 % de holgura; por
debajo del tope de 80 %.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (sin cambios de código de negocio); Bash para scripts de operación; YAML (GitHub Actions, Compose)

**Primary Dependencies**: Docker Engine + Compose v2 en Ubuntu 24.04 LTS; GitHub Actions (`docker/build-push-action`, `shivammathur/setup-php`, k6 en contenedor `grafana/k6`); GHCR; certbot (paquete apt, en el host); fail2ban; `ufw`

**Storage**: MySQL 8.0 y Redis 7 en contenedores con volumen persistente (MySQL) en disco Standard SSD; respaldos `.sql.gz` en `/var/backups/sge` del host

**Testing**: Pest 4 + Pint en CI; verificación del despliegue con la guía [quickstart.md](quickstart.md); prueba de carga k6 (`tests/Load/carga.js`, sin cambios de umbrales); nuevas pruebas Pest solo para el cambio de guarda del `LoadTestSeeder`

**Target Platform**: VM Azure Chile Central, Ubuntu Server 24.04 LTS x64 Gen2; Standard SSD; IP pública estática Standard con nombre DNS; tamaños B2ats_v2 (diario) y D2s_v4 (carga)

**Project Type**: Infraestructura y automatización sobre una aplicación web monolítica existente

**Performance Goals**: SC-006 del Módulo 1 en D2s_v4 (login p95 < 3,5 s, consultas p95 < 1,5 s, fallos < 1 %); desplegar < 15 min, volver atrás < 10 min, restaurar < 15 min

**Constraints**: B2ats_v2 con 1 GB de RAM → el servidor no construye ni ejecuta composer/npm; crédito de 100 USD (tope de proyección 80 %); IP del desarrollador dinámica → SSH sin filtro de origen; GitHub sin acceso al servidor; solo puertos 22/80/443; plazo del curso (diciembre de 2026)

**Scale/Scope**: Un servidor, un entorno (staging), una persona operando; ~25 archivos nuevos (Dockerfiles de producción, compose, scripts, 3 workflows, documentación)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Verificación | Pre-diseño | Post-diseño |
|---|---|:-:|:-:|
| I. Stack fijo | Mismo stack: nginx + PHP-FPM, MySQL 8, Redis, OPcache con `validate_timestamps=0` y `php artisan optimize` en cada arranque/despliegue, Docker Compose, sin Octane. certbot, fail2ban y ufw son herramientas del host, no servicios de aplicación ni código en otro lenguaje; Bash es solo de operación (no backend). | ✅ | ✅ |
| II. Diseño genérico | No se codifica ninguna institución; nombre DNS y valores salen del entorno. | ✅ | ✅ |
| III. RBAC | No se toca autorización. | ✅ N/A | ✅ N/A |
| IV. Alcance fijo | Infraestructura transversal aprobada explícitamente por el desarrollador (registrado en la spec); no agrega funcionalidad de negocio. | ✅ | ✅ |
| V. Pruebas obligatorias | CI ejecuta la suite completa; el único cambio de código (guarda del `LoadTestSeeder`) lleva su prueba Pest; la prueba de carga k6 se ejecuta y documenta en staging. | ✅ | ✅ |
| VI. IA restringida | No aplica; `.env` del servidor ya es el lugar de la API key futura. | ✅ N/A | ✅ N/A |
| VII. Simplicidad | Sin Kubernetes, sin orquestadores, sin Terraform/IaC (el portal manual está dentro del requisito), sin registro propio (GHCR), sin CD automático: despliegue por script con SSH. Se justifica en Complexity Tracking solo la separación en dos imágenes. | ✅ | ✅ (1 justificación) |
| Restricciones técnicas | Secretos solo en `.env` del servidor; `.env.example` actualizado con las variables nuevas; `docker compose up` sigue funcionando en local sin cambios. | ✅ | ✅ |
| Reglas de rendimiento | Sin cambios de consultas. | ✅ N/A | ✅ N/A |
| Definition of Done | Pruebas verdes; endpoint crítico (login) con k6 ejecutado en staging (T de cierre). | ✅ | ✅ |

**Resultado**: sin violaciones; una decisión de diseño justificada en Complexity Tracking.

## Costo estimado hasta el 31/12/2026

Periodo: 16/10 a 31/12/2026 = **77 días = 1848 h** (730 h/mes). Los precios de cómputo son los
consultados por el desarrollador en el portal; **disco e IP son estimaciones** (no provistas) que
deben confirmarse en la calculadora de precios de Azure para Chile Central antes de crear los recursos.

| Concepto | Supuesto | USD |
|---|---|--:|
| B2ats_v2 continua | 9,64/730 = 0,01321 USD/h × (1848 − 16) h | 24,19 |
| D2s_v4 en pruebas | 97,92/730 = 0,1341 USD/h × 16 h (3 pruebas de 4 h + 1 prueba extra de repetición) | 2,15 |
| Disco Standard SSD 30 GiB (E4) | ≈ 3,00 USD/mes × 2,53 meses (se factura aunque la VM esté liberada) | 7,60 |
| IP pública estática (Standard) | ≈ 0,005 USD/h × 1848 h (se factura aunque la VM esté liberada) | 9,24 |
| Tráfico de salida | Dentro del cupo gratuito mensual; reserva | 2,00 |
| **Total** | | **45,18 (45,2 %)** |
| Con 20 % de holgura | | 54,21 (54,2 %) |
| **Tope SC-007 de la spec (80 %)** | | 80,00 |

Lectura: el margen contra el tope es de ≈26 USD **incluso con holgura**. El mayor riesgo no es la
estimación sino **olvidar D2s_v4 encendida**: una semana cuesta 22,54 USD; un mes, 97,92 USD (todo
el crédito). Mitigaciones: alertas 50/75/90 % (50/75/90 USD), recordatorio al final del
procedimiento de prueba y comprobación de tamaño en `deploy.sh` (advierte si detecta D2s_v4 fuera de
una sesión de carga). Si se cambiara a un disco de 64 GiB o a IP/disco más caros, el margen sigue
permitiendo hasta ≈+30 USD antes de tocar el 80 %. Las pruebas de 3–4 h de la petición (sesión
completa: cambio de tamaño, siembra, rampa de ~15 min, recogida de evidencia) se contabilizan enteras
a tarifa D2s_v4 aunque la rampa dure menos.

## Project Structure

### Documentation (this feature)

```text
specs/002-despliegue-ci-staging/
├── plan.md                         # Este archivo
├── research.md                     # Decisiones D-01…D-18 con alternativas
├── data-model.md                   # Entidades: estado en servidor, evidencias, variables
├── quickstart.md                   # Validación de extremo a extremo (SC-001…SC-010)
├── contracts/
│   ├── azure-portal.md             # Paso a paso del portal (región, tamaño, disco, IP, reglas, SSH, alertas, cambio de tamaño)
│   ├── scripts-servidor.md         # CLI de deploy.sh, backup.sh, restore.sh, metrics.sh, bootstrap-servidor.sh
│   ├── workflows-github.md         # ci.yml, release.yml, carga.yml: disparadores, entradas, salidas, permisos
│   └── variables-entorno.md        # .env del servidor: variables, tamaños B2ats_v2 vs D2s_v4
└── tasks.md                        # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
.github/workflows/
├── ci.yml                          # push + pull_request: Pest (MySQL+Redis) y Pint
├── release.yml                     # push de etiqueta v*: construye y publica sge-app y sge-web en GHCR
└── carga.yml                       # workflow_dispatch: k6 1000 VUs contra la URL de staging
docker/
├── php/Dockerfile.prod             # multi-stage: assets (node) → vendor (composer --no-dev) → runtime php-fpm
├── php/entrypoint.prod.sh          # artisan optimize y exec php-fpm
├── php/fpm-pool.conf               # ahora parametrizado por FPM_* (defaults = valores actuales)
├── nginx/Dockerfile.prod           # nginx con public/ copiado de la etapa de assets
└── nginx/prod.conf                 # HTTP→HTTPS, ACME webroot, TLS, cabeceras
.dockerignore
docker-compose.prod.yml             # web, app, db, redis; sin bind del código; solo 80/443 publicados
deploy/
├── bootstrap-servidor.sh           # swap, ufw, fail2ban, Docker, certbot, usuario, cron de respaldo
├── deploy.sh                       # git checkout de la etiqueta, respaldo, pull, migrate, up, salud
├── backup.sh / restore.sh          # mysqldump gz, retención 7 días / restauración
├── metrics.sh                      # CPU, memoria, swap, contenedores y tamaño de VM (IMDS) → CSV
├── env.staging.example             # plantilla del .env del servidor (sin valores reales)
└── cron-sge                        # respaldo diario
docs/despliegue/                    # documentación operativa en español (FR-030): aprovisionamiento, despliegue, vuelta atrás, certificado, cambio de tamaño, carga, respaldo, detener/reactivar
database/seeders/LoadTestSeeder.php # guarda: solo local/staging/testing
tests/Feature/Seeders/…             # prueba de la guarda
.env.example                        # variables nuevas documentadas
README.md                           # enlaza a docs/despliegue
specs/001-auth-roles-rbac/evidencias/   # evidencias k6/SC-007/verificación manual en staging
```

**Structure Decision**: aplicación Laravel monolítica existente; se agregan carpetas de operación
(`deploy/`, `docs/despliegue/`, `.github/workflows/`) y archivos `*.prod` junto a los de desarrollo
sin modificarlos, salvo `fpm-pool.conf` (parametrizado con defaults idénticos) y el guard del seeder.

## Complexity Tracking

| Violación / desvío | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Dos imágenes (`sge-app` y `sge-web`) en vez de una | nginx y PHP-FPM son contenedores distintos (Principio I) y nginx debe servir `public/build` y los estáticos; ambos deben salir de la misma construcción para no desincronizarse | Una sola imagen con ambos procesos exige supervisor; un volumen compartido de `public/` añade estado y orden de arranque frágiles |

Se mantiene el despliegue manual por SSH (sin CD) por decisión del desarrollador: GitHub no recibe
credenciales del servidor.
