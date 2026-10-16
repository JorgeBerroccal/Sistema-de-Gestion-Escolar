# Evidencia del CI (T019, DC-005)

Repositorio: `JorgeBerroccal/Sistema-de-Gestion-Escolar` · Workflow `ci` (`.github/workflows/ci.yml`).

## Ejecuciones en verde (código correcto)

| Run ID | Evento | Commit | `pruebas` | `estilo` |
|---|---|---|---|---|
| 38959649942 | push | `484d6dd` | éxito | éxito |
| 38962914259 | push | `ci: actions a Node 24` ("ci: actions a versiones con Node 24") | éxito | éxito (sin avisos de Node 20) |

La primera ejecución del workflow (run 38959422986, commit `423532e`) falló en el paso de espera de
MySQL/Redis con código 127 (`mysqladmin`/`redis-cli` no existen en el runner); se corrigió en `484d6dd`.

## Fallos deliberados (rama desechable `prueba/ci-fallos`, PR #1 en borrador, cerrado sin integrar)

Commit `a26b488`: una prueba rota (`tests/Feature/PruebaCiFallaTest.php`) y un archivo mal formateado
(`app/PruebaEstilo.php`).

| Run ID | Evento | `pruebas` | `estilo` |
|---|---|---|---|
| 38963132345 | push | **fallo** | **fallo** |
| 38963140667 | pull_request | **fallo** | **fallo** |

Detalle visible en GitHub (leído con `gh run view --log-failed`):

- `pruebas`: `FAIL Tests\Feature\PruebaCiFallaTest`, `Failed asserting that 2 is identical to 3`
  (`tests/Feature/PruebaCiFallaTest.php:5`), y `Tests: 1 failed, 219 passed (632 assertions)` — se ejecutó
  la suite completa, no un subconjunto, contra MySQL y Redis reales.
- `estilo`: `FAIL … 96 files, 1 style issue`, `⨯ app/PruebaEstilo.php class_definition,
  single_space_around_construct, …` — lista los archivos infractores sin modificar el repositorio.
- En el PR #1, `gh pr checks` mostró `pruebas` y `estilo` en fallo.

La rama se borró local y remotamente y el PR se cerró sin integrar.

## Pendiente (manual, lo hace el desarrollador)

- T020: activar en GitHub la protección de `main` exigiendo `ci / pruebas` y `ci / estilo`.
