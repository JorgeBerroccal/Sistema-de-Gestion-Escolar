# Publicación en GHCR y visibilidad (T045–T047, T049)

Fecha: 2026-10-16 · Registro `ghcr.io/jorgeberroccal` · Workflow `release` (`.github/workflows/release.yml`).

| Etiqueta | Commit | Run de `release` | `sge-app` (digest) | `sge-web` (digest) |
|---|---|---|---|---|
| `v0.2.0` | `df80969` (merge del PR #2 en `main`) | 38974579674 (verde) | `sha256:b0b11450c0697212f511a6a752795958a8ba0033f020c7bf217b7d409c3dc127` | `sha256:9c42830ee8139617a85d4fb5d91eeeff0f1600c200366f15610b27ed3997fa4c` |
| `v0.2.9-prueba-falla` | rama desechable `prueba/migracion-falla` (no integrada) | 38975056790 (verde) | `sha256:e5547a5970c5c45ab8bea58b5dd0e892d5d738fdc72e2663488406e5fafbc02f` | `sha256:0663d150e94e6c50b7224d765c889d7d4e4ffaa085734c1c082da10fd734a9c2` |

## T046 / FR-006a — visibilidad

Con `DOCKER_CONFIG` vacío (sin sesión en ghcr.io), `docker pull` de `sge-app:v0.2.0`, `sge-web:v0.2.0` y de las
dos imágenes de `v0.2.9-prueba-falla` funcionó sin `denied`/`unauthorized`: los paquetes son públicos y no
hizo falta cambiar la visibilidad en GitHub.

## T047 — arranque local de la imagen publicada

Pila de producción (`docker-compose.prod.yml`, `.env` temporal fuera del repositorio, certificado
autofirmado, puertos 9080/9443) con `SGE_VERSION=v0.2.0` y las imágenes descargadas del registro:
`GET https://localhost:9443/up` → `{"status":"ok","version":"v0.2.0"}`; migraciones y `db:seed --force`
correctos. Pila y volúmenes eliminados al terminar.

## T049 — etiqueta de ensayo de migración fallida (FR-031)

`database/migrations/2026_10_16_000001_prueba_migracion_falla.php` añade la columna
`usuarios.columna_prueba_falla` y después lanza `RuntimeException`. Solo existe en la etiqueta
`v0.2.9-prueba-falla` (la rama `prueba/migracion-falla` es local y no se integra a `main`). Se borrarán la
etiqueta y la rama tras el ensayo T069.

## T048 — etiqueta v0.2.1

Commit `5dcd794` (merge del PR #3 en `main`), etiqueta `v0.2.1`, run de `release` 38976626788 (verde). Las
imágenes `sge-app:v0.2.1` y `sge-web:v0.2.1` existen en GHCR y su manifiesto responde sin sesión. Sirve para
probar el despliegue y la vuelta atrás (`/up` pasa de `v0.2.0` a `v0.2.1`).

## Versiones de prueba en GHCR

2026-10-16: con el token de `gh` ampliado (`read:packages`, `delete:packages`) se borraron las versiones
etiquetadas `v0.2.0-rc1`, `rc2`, `rc3` y `rc4` de `sge-app` y de `sge-web` (8 en total). Se conservaron `cache`,
`v0.2.0`, `v0.2.1` y `v0.2.9-prueba-falla`; comprobado por manifiesto anónimo (todas responden; `rc1` ya no
existe). Las versiones sin etiqueta (manifiestos hijos) no se borraron para no romper las imágenes conservadas.
