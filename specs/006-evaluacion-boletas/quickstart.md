# Quickstart: validar Evaluación y Boletas

**Feature**: `006-evaluacion-boletas`

Guía de validación; reglas en [data-model.md](data-model.md) y contratos en [contracts/](contracts/).

## Prerrequisitos

```bash
docker compose up -d
docker compose exec app composer install     # incluye barryvdh/laravel-dompdf
docker compose exec app php artisan migrate:fresh --seed
# En local, DatabaseSeeder carga también EvaluacionDemoSeeder (datos FICTICIOS). A mano, por ejemplo en staging:
docker compose exec app php artisan sge:sincronizar-base
docker compose exec app php artisan db:seed --class=EvaluacionDemoSeeder
```

## 1. Pruebas automáticas

```bash
docker compose exec app php artisan test --filter=Evaluacion
docker compose exec app php artisan test        # suite completa, incluye SinCondicionalesDeRolTest
```

Esperado: todo en verde; incluye promedios con notas vacías y escala literal (SC-004), concurrencia (sin duplicados),
denegaciones (SC-003) y la boleta en PDF (SC-006).

## 2. Prueba de humo manual en staging (~10 minutos, Constitución 2.2.0)

| # | Como | Acción | Resultado esperado |
|---|---|---|---|
| 1 | Administrador | Menú → Registro de notas; elegir sección y curso; crear dos criterios (60 % y 40 %) | Esquema guardado; con 70/40 se rechaza |
| 2 | Administrador | Escribir notas de la sección; «Guardar notas» (formulario clásico); reabrir | Notas y promedios persistidos; una nota fuera de escala no guarda nada |
| 3 | Administrador | Menú → Cierres de periodo; cerrar el periodo de la sección | Estado «cerrado» |
| 4 | Docente (rol limitado) | Abrir la misma sección y curso | Tabla de solo lectura; sin guardar |
| 5 | Padre de Familia (rol limitado) | Menú → Mis notas; elegir un hijo | Ve sus cursos, criterios y promedios; ningún otro alumno |
| 6 | Padre de Familia | «Descargar boleta» del periodo | PDF con alumno, cursos, notas, promedios y asistencia; con escala literal, calificativos en letra |
| 7 | Administrador | Reabrir el periodo; el Padre vuelve a Mis notas | El periodo ya no aparece |

Registrar la evidencia en `specs/006-evaluacion-boletas/evidencias/staging.md` (versión `v0.6.0`, 7 permisos nuevos →
30 en total).

## 3. Sesión única de carga k6 (17–18/11, sobre `v0.6.0` ya desplegada y con humo hecho)

Ver [contracts/prueba-de-carga.md](contracts/prueba-de-carga.md) y `docs/despliegue/prueba-de-carga.md`.

```bash
docker compose exec app php artisan db:seed --class=EvaluacionCargaSeeder   # local / staging
docker compose --profile load run --rm k6 run -e SMOKE=1 /scripts/evaluacion.js   # ensayo (10 VU): login, consulta, boleta y POST del formulario
# En staging: GitHub → Actions → carga (script = evaluacion.js), primero «humo» y luego la rampa completa de 1000 VU.
```
