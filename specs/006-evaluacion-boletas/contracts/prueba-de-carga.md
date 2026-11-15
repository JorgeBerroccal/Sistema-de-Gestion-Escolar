# Contrato: Sesión única de carga k6

**Feature**: `006-evaluacion-boletas` · Constitución 2.2.0, Principio V · Responde a FR-016 y SC-005.

Script: `tests/Load/evaluacion.js` (reutiliza el login de `tests/Load/carga.js`). Procedimiento de la sesión:
`docs/despliegue/prueba-de-carga.md` (workflow `carga` con `script = evaluacion.js`; VM en `B2als_v2`, perfil `PERFIL_TAMANO=carga`, 2 vCPU / 4 GB, y vuelta a `B2ats_v2` / `diario` al terminar). Calendario: 13/11, sobre `v0.6.0` ya desplegada.

## Escenario (lo que hace cada usuario virtual)

| Flujo | Quién | Qué hace por iteración | Proporción | Etiqueta |
|---|---|---|---|---|
| Login | todos, en su primera iteración | `GET /login` + `POST /login` (`_token`) | una vez por VU | `login` |
| Consulta de notas | `carga0001…1000` (rol de carga con `notas.ver_propia` y `boletas.ver_propia`, vinculadas a un alumno ficticio con periodo cerrado) | `GET /notas/mias` | **~97 %** de las iteraciones | `consulta` |
| Boleta PDF | las mismas cuentas | `GET /boletas/{estudiante}/{periodo}` | **~3 %** (1 de cada ~30 iteraciones) | `boleta` |
| Registro de notas | **20** cuentas `cargadoc01…20` (rol de carga con `notas.registrar_propia` y `docencia.dictar`), una sección ficticia de 30 alumnos × 5 criterios cada una | `GET /notas/registro/{seccion}/{curso}` (lee `_token` y campos) y `POST` del formulario con las 150 notas | 1 guardado por docente cada ~60 s | `notas` |

Pausa entre iteraciones de familias: 10–30 s (igual que `carga.js`). Con 1000 VU en el pico son ~50 iteraciones/s:
~48 consultas/s y ~1,5 boletas/s (la generación de PDF es la petición más costosa en CPU), más ~0,3 guardados/s.

## Umbrales

`carga` y `diario` son **tamaños de la VM** (la rampa solo corre en `B2als_v2`; `B2ats_v2` es el tamaño diario), no perfiles de k6.
El script distingue dos modalidades:

| Métrica | Humo (`-e SMOKE=1`, 10 VU: «condiciones normales») | Rampa completa (hasta 1000 VU en `B2als_v2`) |
|---|---|---|
| `http_req_failed` | < 1 % | < 1 % |
| `http_req_duration{name:login}` | p95 < 3,5 s (heredado del Módulo 1) | p95 < 3,5 s |
| `http_req_duration{name:consulta}` | p95 < 1,5 s (heredado) | p95 < 2 s |
| `http_req_duration{name:notas}` | p95 < 3 s | p95 < 4 s |
| `http_req_duration{name:boleta}` | **p95 < 5 s** (SC-006) | p95 < 8 s |

Los umbrales del pico son más holgados porque la CPU de 2 vCPU se satura antes; la generación de la boleta es la petición
más costosa en CPU. Si los créditos de CPU se agotan durante la rampa, la evidencia se guarda y se analiza con el minuto en que
la CPU quedó limitada (como en `docs/despliegue/prueba-de-carga.md`); un problema hallado se corrige en `v0.6.1`.

## Cookies y chequeos (lección del ensayo local)

k6 **vacía las cookies entre iteraciones** por defecto. `evaluacion.js` activa `noCookiesReset: true` (el login ocurre una sola
vez por VU) y sus chequeos comprueban que la respuesta **no sea la página de login** y que contenga lo esperado
(`Mis notas`, `application/pdf`, `Notas guardadas`): sin eso una sesión perdida devuelve 200 de la página de login y las
métricas parecerían buenas. `tests/Load/carga.js` (Módulo 1) no tiene `noCookiesReset`: ver el hallazgo en el informe del Módulo 5.

## Datos y seguridad

`EvaluacionCargaSeeder` (solo `local`, `staging`, `testing`; rechaza producción) crea los datos ficticios y las
cuentas de carga; las cuentas `carga*` se activan solo durante la sesión y se desactivan al terminar (Ley 29733).
Modo de ensayo: `docker compose --profile load run --rm k6 run -e SMOKE=1 /scripts/evaluacion.js` (10 VU, ~35 s) antes de la rampa completa; `-e PROPORCION_BOLETA=0.5` fuerza más boletas para probar el flujo.
Los resultados (`k6-resumen.json`, `k6-salida.txt`, `meta.json`, `meta-servidor.json`) se documentan en
`specs/006-evaluacion-boletas/evidencias/`.
