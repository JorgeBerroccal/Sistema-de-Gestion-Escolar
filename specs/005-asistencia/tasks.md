---

description: "Task list for 005-asistencia (Módulo 4, Asistencia)"
---

# Tasks: Asistencia

**Input**: Design documents from `/specs/005-asistencia/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Obligatorios (Constitución, Principio V): cada tarea de código lleva su prueba Pest; cada regla de autorización tiene prueba de acceso permitido y denegado, y cada regla de validación una de caso válido e inválido (FR-013). Sin k6 (Constitución 2.2.0).

**Organization**: por historia de usuario (US1–US4 de spec.md). US1 es el MVP; US2, US3 y US4 leen o amplían lo que US1 guarda.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede hacer en paralelo (archivos distintos, sin dependencia pendiente)
- **[Story]**: US1 tomar asistencia · US2 consulta propia y de los hijos · US3 justificar faltas · US4 reportes
- Comandos dentro de Docker: `docker compose exec app …`

## Path Conventions

Monolito Laravel 13 (ver plan.md): servicios en `app/Asistencia/`, modelos en `app/Models/`, componentes en `app/Livewire/Asistencia/`, vistas en `resources/views/livewire/asistencia/`, pruebas en `tests/Feature/Asistencia/` y `tests/Unit/Asistencia/`. **Datos de ejemplo siempre ficticios** (FR-015).

---

## Phase 1: Setup

- [x] T001 Crear los directorios `app/Asistencia/`, `app/Livewire/Asistencia/{Tomar,Propia,Reportes}`, `resources/views/livewire/asistencia/`, `tests/Feature/Asistencia/`, `tests/Unit/Asistencia/` y `specs/005-asistencia/evidencias/`

---

## Phase 2: Foundational (bloquea todas las historias)

- [x] T002 Migración `database/migrations/2026_11_06_000001_create_estados_asistencia_table.php`: `codigo` string(20) único, `nombre` string(40), `cuenta_asistencia` boolean, `cuenta_falta` boolean, `justificado_id` FK nullable autorreferenciada → `estados_asistencia` (restrict, con índice), `orden` tinyint unsigned, `activo` boolean por defecto sí (data-model.md)
- [x] T003 Migración `2026_11_06_000002_create_asistencias_table.php`: `seccion_id` FK restrict → secciones, `alumno_id` FK restrict → alumnos, `fecha` date, `estado_id` FK restrict → estados_asistencia (con índice), `observacion` string(255) nullable, `motivo_justificacion` string(255) nullable, `registrado_por` FK restrict → usuarios; **único `(seccion_id, alumno_id, fecha)`**, índices `(alumno_id, fecha)` y `(seccion_id, fecha)`; verificar con `migrate:fresh` (R-02)
- [x] T004 [P] Modelos `app/Models/EstadoAsistencia.php` (relación `justificado`, casts booleanos) y `app/Models/Asistencia.php` (relaciones `seccion`, `alumno`, `estado`, `registrador`; cast de `fecha` a date; trait `RequierePertenencia`) y fábricas `database/factories/{EstadoAsistenciaFactory,AsistenciaFactory}.php` con datos ficticios
- [x] T005 `database/seeders/EstadosAsistenciaSeeder.php` idempotente (`updateOrCreate` por código): presente y tarde (`cuenta_asistencia`), falta (`cuenta_falta`, `justificado_id` → falta_justificada), falta_justificada; añadirlo a `SincronizarBase::SEEDERS` en `app/Console/Commands/SincronizarBase.php`; prueba en `tests/Feature/Console/` de que corre y no duplica (R-01)
- [x] T006 Ampliar `RolesYPermisosSeeder::PERMISOS` con los 4 permisos de `contracts/permisos-y-pertenencia.md` (`asistencia.ver_todas`, `asistencia.gestionar`, `asistencia.registrar_propia`, `asistencia.ver_propia`) y sus roles por defecto, conservando «asigna solo al crear el permiso»; prueba `tests/Feature/Asistencia/PermisosAsistenciaSeederTest.php` (asignaciones, idempotencia, no pisa cambios del Administrador)
- [x] T007 [P] `app/Autorizacion/Pertenencia/Reglas/AsistenciaRegla.php` según la tabla del contrato (reutiliza `MatriculaRegla::seccionesDelDocente` y `alumnosPropios`; unión de condiciones; fail-closed); registrarla en `config/pertenencia.php`; prueba `tests/Feature/Asistencia/Pertenencia/AsistenciaReglaTest.php` (cada combinación de permisos, usuario sin vínculos → vacío, regla no registrada → 403)
- [x] T008 Grupo de rutas `/asistencia` (middleware `auth` + `activo`) en `routes/web.php` **sin rutas hijas** y los ítems de `config/menu.php` (Tomar asistencia, Mi asistencia, Reportes de asistencia) según `contracts/rutas-web.md`; cada historia registra sus rutas junto a su componente

**Checkpoint**: `migrate:fresh --seed` y la suite de los Módulos 1–3 siguen en verde.

---

## Phase 3: User Story 1 — Tomar la asistencia diaria (P1) 🎯 MVP

**Goal**: el Docente (o el Administrador) registra y corrige la asistencia de una sección por fecha.

**Independent Test**: sección con cinco alumnos matriculados; registrar hoy con una falta y una tardanza, reabrir y corregir.

- [x] T009 [P] [US1] `tests/Feature/Asistencia/AsistenciaServiceTest.php`: lista de la fecha (solo matrícula vigente en esa fecha; retirado aparece hasta el día anterior a `fecha_estado`; matriculado a mitad de año desde `fecha_matricula`; conserva a quien ya tiene registro); guardado crea un registro por alumno; reguardar reemplaza sin duplicar; **concurrencia** (dos guardados → un registro por alumno, prevalece el último); todo o nada ante un estado inválido; rechazo de fecha futura, sábado, domingo, fuera del año y año no vigente; **a las 20:00 en Lima (01:00 UTC del día siguiente) «hoy» sigue siendo el día de Lima y mañana se rechaza**; alumno trasladado el día D aparece hasta D−1 en la sección de origen y desde D en la de destino; sección sin alumnos no guarda; mensajes sin nombres ni documentos (R-12)
- [x] T010 [P] [US1] `tests/Feature/Asistencia/Acceso/TomarAccesoTest.php`: Docente con asignación toma; Docente sin asignación → 403 al abrir y al guardar; Administrador toma en cualquier sección; Director, Padre y Alumno → 403; rol creado en la prueba con `asistencia.registrar_propia` funciona y sin permiso no
- [x] T011 [US1] `app/Asistencia/AsistenciaService.php`: `config/asistencia.php` (`zona_horaria` = `env('ASISTENCIA_ZONA_HORARIA', 'America/Lima')`, y la variable en `.env.example`; sin tocar `app.timezone`), `validarFecha(Seccion, Carbon)` (no futura según esa zona, dentro de `fecha_inicio`–`fecha_fin` del año, año vigente, no `isWeekend`), `nominaDeLaFecha(Seccion, Carbon)` (R-03), `guardar(Seccion, Carbon, array $filas, Usuario)` con `upsert` en transacción y `registrado_por`; estado válido y activo del catálogo; `observacion` máx. 255 (R-02, R-04, R-05)
- [x] T012 [US1] `app/Livewire/Asistencia/Tomar/Index.php` y vista: secciones del año vigente tomables por el usuario (`gestionar`: todas; `registrar_propia`: con asignación), paginadas y con `with()`; `Tomar/Show.php` y vista con `?fecha=` (por defecto hoy), lista con selector de estado y observación, aviso y sin botón en sábado/domingo/futuro/año no vigente, guardado en una operación; rutas `asistencia.tomar.index` y `asistencia.tomar.show` con el doble permiso comprobado en `mount()` y pertenencia antes de cargar relaciones
- [x] T013 [US1] Hacer pasar T009–T010 y revisar que el menú «Tomar asistencia» aparece solo con los permisos correctos

**Checkpoint**: US1 funciona sola; es el MVP.

---

## Phase 4: User Story 2 — Consulta propia y de los hijos (P1)

**Goal**: el Alumno ve su asistencia y el Padre la de cada hijo, por mes, con resumen.

**Independent Test**: Padre con dos hijos y asistencia registrada ve ambos con resumen y a nadie más; el Alumno solo la suya.

- [x] T014 [P] [US2] `tests/Feature/Asistencia/ResumenAsistenciaTest.php`: conteos por estado y porcentaje = `cuenta_asistencia`/(`cuenta_asistencia`+`cuenta_falta`); las faltas justificadas y los días sin registro no entran en el denominador; denominador 0 → «sin datos» (no 0 %); detalle por mes
- [x] T015 [P] [US2] `tests/Feature/Asistencia/Acceso/PropiaAccesoTest.php`: Padre ve a sus hijos y no a ajenos (URL directa → 403); Alumno ve solo la suya; usuario sin vínculos → lista vacía; ningún control de edición para ellos; Director y Docente → 403 en esta pantalla
- [x] T016 [US2] `app/Asistencia/ResumenAsistencia.php`: `delAlumno(Alumno, desde, hasta)` y `porMes(Alumno, año, mes)` con agregados SQL (`SUM(CASE …)` / `GROUP BY` con join a `estados_asistencia`, sin cargar colecciones) (R-08)
- [x] T017 [US2] `app/Livewire/Asistencia/Propia/Index.php` y vista: selector de hijo (o el propio alumno), mes y resumen del año, filtrando con `Asistencia::visiblePara()`; ruta `asistencia.propia.index` con `can:asistencia.ver_propia`
- [x] T018 [US2] Hacer pasar T014–T015

---

## Phase 5: User Story 3 — Justificar faltas (P2)

**Goal**: Administrador y Docente de la sección justifican y revierten faltas; la familia solo lo ve.

**Independent Test**: registrar una falta, justificarla con motivo, comprobar el resumen y revertirla.

- [x] T019 [P] [US3] `tests/Feature/Asistencia/JustificarTest.php`: justificar falta → «falta justificada» con motivo obligatorio (máx. 255); justificar un registro que no es falta → rechazo; revertir restaura «falta» y limpia el motivo; el resumen deja de contarla como falta; Docente sin asignación, Padre, Alumno y Director → 403 y sin controles
- [x] T020 [US3] Ampliar `AsistenciaService` con `justificar(Asistencia, string $motivo, Usuario)` y `revertir(Asistencia, Usuario)` usando `justificado_id` (R-09); solo año vigente; autorizar por pertenencia de la sección
- [x] T021 [US3] Ampliar `Tomar/Show` con las acciones justificar/revertir en cada falta (visibles solo con permiso de escritura sobre la sección); hacer pasar T019

---

## Phase 6: User Story 4 — Reportes por sección y por alumno (P2)

**Goal**: Administrador, Director y Docente consultan totales, porcentaje y ranking de faltas por sección y rango o periodo.

**Independent Test**: asistencia de dos secciones en varias fechas; el reporte de una sección coincide con los registros.

- [x] T022 [P] [US4] `tests/Feature/Asistencia/ReportesTest.php`: totales por estado, porcentaje y ranking de faltas injustificadas (las justificadas no cuentan) coinciden con la muestra (SC-004); rango libre y periodo de evaluación (R-10); rango sin registros → «sin datos»; detalle de un alumno
- [x] T023 [P] [US4] `tests/Feature/Asistencia/Acceso/ReportesAccesoTest.php`: Director y Administrador ven todas las secciones, el Director sin controles de edición; Docente solo las suyas (otra → 403); Padre y Alumno → 403; quitar `asistencia.ver_todas` al Director corta su acceso
- [x] T024 [US4] Ampliar `ResumenAsistencia` con `deSeccion(Seccion, desde, hasta)` (totales por estado, porcentaje) y `rankingFaltas(Seccion, desde, hasta)` paginado, ordenado por `cuenta_falta`, con agregados SQL
- [x] T025 [US4] `app/Livewire/Asistencia/Reportes/Index.php` y `Reportes/Alumno.php` con vistas: elección de sección visible, rango o periodo (limitado al año de la sección), totales, ranking paginado y enlace al detalle; rutas `asistencia.reportes.index` y `asistencia.reportes.alumno`; hacer pasar T022–T023

---

## Phase 7: Polish y verificación transversal

- [x] T026 [P] `database/seeders/AsistenciaDemoSeeder.php`: asistencia **ficticia** para las matrículas de `MatriculaDemoSeeder` en días hábiles pasados del año vigente; no crea cuentas; **rechaza `APP_ENV=production`**; se llama desde `DatabaseSeeder` solo en `local`; prueba `tests/Feature/Seeders/AsistenciaDemoSeederTest.php` (carga, idempotente, sin usuarios nuevos, rechazo en producción) (FR-015, R-11)
- [x] T027 [P] `tests/Feature/Rendimiento/ReportesAsistenciaTest.php` (SC-005): sección de 30 alumnos con ~200 días de registros; reporte y detalle por debajo de 3 s, número de consultas constante; confirmar con `EXPLAIN` el uso de índices
- [x] T028 [P] `tests/Feature/Asistencia/GenericidadTest.php`: ningún nombre de nivel, grado, sección o institución de ejemplo en `app/`, `resources/views` y `config/`; los estados salen de la tabla; confirmar que `SinCondicionalesDeRolTest` sigue en verde
- [x] T029 [P] `tests/Feature/Asistencia/RecorridoQuickstartTest.php`: recorrido automatizado del quickstart §2 con los componentes reales (toma, sábado rechazado, justificación, reporte, 403 de Padre)
- [x] T030 Actualizar `README.md` (módulo 4, seeder ficticio, regla sábado/domingo y exclusión de feriados) y `docs/despliegue/` (estados de asistencia en la sincronización base y carga de asistencia ficticia en staging)
- [ ] T031 Ejecutar la suite completa, `pint --test` y `/speckit-analyze` final; corregir hallazgos
- [ ] T032 (manual) Etiquetar `v0.5.0` sobre `main` tras integrar la rama, esperar a `release` y desplegar con `deploy/deploy.sh`; comprobar que los permisos suman 24; cargar `AsistenciaDemoSeeder`; ejecutar la **prueba de humo de ~10 min** de `quickstart.md` §2 con el Administrador y un Padre ficticio. **En staging solo datos ficticios.** Registrar en `specs/005-asistencia/evidencias/staging.md` dentro del primer commit de la rama del Módulo 5 (SC-002, SC-003)

---

## Dependencies & Execution Order

- Fase 1 → Fase 2 (bloquea todo) → US1 → US2 → US3 → US4 → Polish.
- US2 y US4 solo necesitan los datos de US1 (en pruebas, vía fábricas), por lo que pueden avanzar en paralelo a US3; US3 amplía `AsistenciaService` y `Tomar/Show` (T011–T012).
- Dentro de cada historia: pruebas (deben fallar) → servicios → componentes → rutas → verde.
- Migraciones en orden `000001`–`000002` (estados → asistencias); verificar con `migrate:fresh` al terminar T003.

## Parallel Opportunities

- Fase 2: T004 y T007 en paralelo tras las migraciones.
- Cada historia: sus dos pruebas [P] en paralelo (T009–T010, T014–T015, T022–T023).
- Polish: T026–T029 en paralelo.

## Implementation Strategy

- **MVP**: Fases 1–3 (US1). US2 da valor a las familias; US3 y US4 completan el módulo.
- **Orden de recorte si hay retraso** (calendario aprobado): primero la amplitud de Asistencia — detalle por alumno y periodo de evaluación en reportes (T025), luego US3 (T019–T021) — antes que reglas de seguridad; nunca recortar T007, T010, T015, T023 ni la regla de datos ficticios (T026).
- Objetivo de calendario: despliegue `v0.5.0` el jueves 12/11.
