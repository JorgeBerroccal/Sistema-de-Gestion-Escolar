# Contrato: Sesión única de carga k6

**Feature**: `006-evaluacion-boletas` · Constitución 2.2.0, Principio V · Responde a FR-016 y SC-005.

Script: `tests/Load/evaluacion.js` (reutiliza el login de `tests/Load/carga.js`). Procedimiento de la sesión:
`docs/despliegue/prueba-de-carga.md` (perfil `carga`, luego `diario`). Calendario: 17–18/11. Servidor: 2 vCPU / 4 GB.

## Escenario (lo que hace cada usuario virtual)

| Flujo | Quién | Qué hace por iteración | Proporción | Etiqueta |
|---|---|---|---|---|
| Login | todos, en su primera iteración | `GET /login` + `POST /login` (`_token`) | una vez por VU | `login` |
| Consulta de notas | `carga0001…1000` (rol de carga con `notas.ver_propia` y `boletas.ver_propia`, vinculadas a un alumno ficticio con periodo cerrado) | `GET /notas/mias` | **~97 %** de las iteraciones | `consulta` |
| Boleta PDF | las mismas cuentas | `GET /boletas/{alumno}/{periodo}` | **~3 %** (1 de cada ~30 iteraciones) | `boleta` |
| Registro de notas | **20** cuentas `cargadoc01…20` (rol de carga con `notas.registrar_propia` y `docencia.dictar`), una sección ficticia de 30 alumnos × 5 criterios cada una | `GET /notas/registro/{seccion}/{curso}` (lee `_token` y campos) y `POST` del formulario con las 150 notas | 1 guardado por docente cada ~60 s | `notas` |

Pausa entre iteraciones de familias: 10–30 s (igual que `carga.js`). Con 1000 VU en el pico son ~50 iteraciones/s:
~48 consultas/s y ~1,5 boletas/s (la generación de PDF es la petición más costosa en CPU), más ~0,3 guardados/s.

## Umbrales (por perfil)

| Métrica | `carga` (pico 1000 VU) | `diario` (SC-006, condiciones normales) |
|---|---|---|
| `http_req_failed` | < 1 % | < 1 % |
| `http_req_duration{name:login}` | p95 < 3,5 s (heredado del Módulo 1) | p95 < 3,5 s |
| `http_req_duration{name:consulta}` | p95 < 2 s | p95 < 1,5 s (heredado) |
| `http_req_duration{name:notas}` | p95 < 4 s | p95 < 3 s |
| `http_req_duration{name:boleta}` | p95 < 8 s | **p95 < 5 s** (SC-006) |

Los umbrales de pico son más holgados porque la CPU de 2 vCPU se satura antes que en `diario`; si falla la boleta
en el pico pero cumple en `diario`, se documenta con el minuto en que la CPU quedó limitada (como en
`docs/despliegue/prueba-de-carga.md`) y no se repite la sesión.

## Datos y seguridad

`EvaluacionCargaSeeder` (solo `local`, `staging`, `testing`; rechaza producción) crea los datos ficticios y las
cuentas de carga; las cuentas `carga*` se activan solo durante la sesión y se desactivan al terminar (Ley 29733).
Modo de ensayo: `k6 run -e SMOKE=1 tests/Load/evaluacion.js` (10 VU, ~30 s) antes de la rampa completa.
Los resultados (`k6-resumen.json`, `k6-salida.txt`, `meta.json`, `meta-servidor.json`) se documentan en
`specs/006-evaluacion-boletas/evidencias/`.
