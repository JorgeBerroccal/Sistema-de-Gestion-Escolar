# Verificación del workflow `release` con etiquetas de prueba (Fase 7, parcial)

Fecha: 2026-10-16 · Etiquetas desechables `v0.2.0-rc1` … `v0.2.0-rc4` empujadas desde la rama
`002-despliegue-ci-staging` (ya borradas del repositorio). No se tocó `main` ni se crearon las etiquetas
finales `v0.2.0` / `v0.2.1`.

| Run | Etiqueta | Caché de capas | Duración total | `sge-app` | `sge-web` |
|---|---|---|--:|--:|--:|
| 38966604549 | rc1 | `type=gha` (vacía) | 133 s | 109 s | 6 s |
| (rc2) | rc2 | `type=gha` | **210 s** | 176 s | 11 s |
| (rc3) | rc3 | `type=registry` (vacía) | 131 s | 109 s | 5 s |
| (rc4) | rc4 | `type=registry` | **50 s** | **5 s** (26 pasos `CACHED`) | 13 s |

## Hallazgo: la caché `type=gha` no sirve con etiquetas

rc2 no reutilizó nada de rc1 (incluso salió más lenta). `gh api …/actions/caches` mostró las entradas bajo
`refs/tags/v0.2.0-rc2`: la caché de GitHub Actions solo se restaura desde la misma referencia, la rama base
o la rama por defecto, y **cada etiqueta de versión es una referencia distinta**. Se sustituyó por caché en
el registro (`type=registry,ref=ghcr.io/<propietario>/sge-app:cache`, `mode=max`), que se comparte entre
etiquetas: 131 s en frío → 50 s con caché (sge-app 5 s). Las extensiones de PHP, composer y npm (la parte
pesada) solo se vuelven a construir si cambian Dockerfile, `composer.lock` o `package-lock.json`.

La construcción en el runner de GitHub tarda ~2 min en frío (frente a ~7 min en el equipo local).

## Visibilidad de los paquetes en GHCR

Descarga **anónima** (`docker logout ghcr.io` + `docker pull`) de `sge-app:v0.2.0-rc1` y
`sge-web:v0.2.0-rc1`: correcta. Consulta anónima del manifiesto (API del registro): HTTP 200 para
`sge-app` y `sge-web` con las etiquetas `v0.2.0-rc4` y `cache`. Es decir, GHCR creó los paquetes ya
**públicos** (heredan la visibilidad del repositorio público). El token de `gh` no tiene el alcance
`read:packages`/`delete:packages`, así que no se pudo consultar la visibilidad por API ni borrar las
versiones de prueba (`v0.2.0-rc1…rc4`, `cache`); quedan en los paquetes y pueden eliminarse desde GitHub →
Packages. **T046 sigue pendiente** para las etiquetas finales (repetir el pull anónimo con `v0.2.0`).
