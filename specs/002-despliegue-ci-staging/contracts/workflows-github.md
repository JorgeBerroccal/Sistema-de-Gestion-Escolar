# Contrato: workflows de GitHub Actions

## `ci.yml` — nombre "ci"

- **Disparadores**: `push` (cualquier rama) y `pull_request`.
- **Permisos**: `contents: read`. **Secretos**: ninguno.
- **Servicios**: `mysql:8.0` (usuario/clave de prueba, healthcheck) y `redis:7-alpine`.
- **Pasos**: checkout → PHP 8.3 (extensiones `pdo_mysql, bcmath, intl, zip, redis`) → caché de Composer
  → `composer install --no-interaction --prefer-dist` → Node 20 + `npm ci` + `npm run build` → crear
  `.env` de pruebas desde `.env.example` y `key:generate` → crear BD `sge_testing` → **Pest** (job
  `pruebas`) → **Pint** `--test` (job `estilo`, en paralelo, solo requiere composer).
- **Salida**: estado verde/rojo de `ci / pruebas` y `ci / estilo` en el commit y el PR; Pest en formato
  `--ci`; los fallos de Pint listan archivos.
- **Garantía** (SC-005): corre en el 100 % de los push y PR; un fallo deliberado marca el estado como rojo.

## `release.yml` — nombre "release"

- **Disparadores**: `push` de etiquetas `v*` y `workflow_dispatch` (entrada `etiqueta` existente).
- **Permisos**: `contents: read`, `packages: write`. **Secretos**: solo `GITHUB_TOKEN` automático.
- **Pasos**: checkout de la etiqueta → login a `ghcr.io` → Buildx → construir y publicar
  `ghcr.io/<propietario-en-minúsculas>/sge-app:<etiqueta>` (`docker/php/Dockerfile.prod`) y
  `.../sge-web:<etiqueta>` (`docker/nginx/Dockerfile.prod`), `linux/amd64`, caché de capas en GHA.
- **Condición**: no corre en pull requests; opcionalmente exige que `ci` esté verde para el SHA
  etiquetado (job que consulta el estado antes de publicar).
- **Salida**: imágenes con la etiqueta; resumen del job con los *digests* y un recordatorio, solo en
  la primera publicación de cada paquete, de **verificar y poner en público** la visibilidad en GHCR
  (el paquete se crea privado por defecto).

## `carga.yml` — nombre "carga"

- **Disparador**: solo `workflow_dispatch`. Nunca con push/PR.
- **Entradas**: `base_url` (por defecto `vars.STAGING_URL`), `tamano_vm` (texto, obligatorio; p. ej.
  `Standard_D2s_v5`), `version` (etiqueta desplegada, obligatorio), `humo` (boolean, default false →
  rampa completa).
- **Permisos**: `contents: read`. **Variables**: `vars.STAGING_URL`. **Secretos**: ninguno.
- **Guardas**: falla pronto si `base_url` ≠ `vars.STAGING_URL`, si `STAGING_URL` no está definida, o
  si `GET {base_url}/up` ≠ 200; imprime el aviso de que se debe haber lanzado `metrics.sh start`.
- **Pasos**: checkout → `docker run grafana/k6 run -e BASE_URL=… [-e SMOKE=1] --summary-export … /scripts/carga.js`
  → subida del artefacto **siempre** (`if: always()`), retención 90 días.
- **Artefacto `evidencia-carga-<run_id>`**: `k6-resumen.json`, `k6-salida.txt`, `meta.json`
  (`base_url`, `version`, `tamano_vm`, `sha`, `run_id`, fecha UTC, modalidad).
- **Resultado**: job rojo si algún umbral de SC-006 falla; igual se guarda la evidencia (FR-022).
