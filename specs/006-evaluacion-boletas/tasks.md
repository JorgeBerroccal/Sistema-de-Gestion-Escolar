---

description: "Task list for 006-evaluacion-boletas (Módulo 5, Evaluación y Boletas)"
---

# Tasks: Evaluación y Boletas

**Input**: Design documents from `/specs/006-evaluacion-boletas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Obligatorios (Constitución, Principio V): cada tarea de código lleva su prueba Pest; cada regla de autorización tiene prueba de acceso permitido y denegado, y cada regla de validación una de caso válido e inválido (FR-014, SC-003). **Sesión única de carga k6 en este módulo** (Constitución 2.2.0, FR-016).

**Organization**: por historia de usuario (US1–US5 de spec.md). US1 es el MVP; US2–US5 leen o amplían lo que US1 guarda. La publicación (cierre) afecta a US2 y US4: su modelo y regla viven en Foundational y la interfaz de cerrar/reabrir en US3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede hacer en paralelo (archivos distintos, sin dependencia pendiente)
- **[Story]**: US1 registrar notas · US2 consulta propia y de los hijos · US3 cerrar y publicar periodos · US4 boleta PDF · US5 reporte por sección
- Comandos dentro de Docker: `docker compose exec app …`

## Path Conventions

Monolito Laravel 13 (ver plan.md): servicios en `app/Evaluacion/`, modelos en `app/Models/`, controladores en `app/Http/Controllers/`, componentes en `app/Livewire/Evaluacion/`, vistas en `resources/views/livewire/evaluacion/` y `resources/views/boletas/`, pruebas en `tests/Feature/Evaluacion/` y `tests/Unit/Evaluacion/`. **Datos de ejemplo siempre ficticios** (FR-017). **Los equivalentes AD=4, A=3, B=2, C=1 son configuración de la institución, no una regla del Currículo Nacional** (decisión del responsable, R-03).

---

## Phase 1: Setup

- [x] T001 Crear los directorios `app/Evaluacion/`, `app/Livewire/Evaluacion/{Registro,Cierres,Propia,Reportes}`, `resources/views/livewire/evaluacion/`, `resources/views/boletas/`, `tests/Feature/Evaluacion/`, `tests/Unit/Evaluacion/` y `specs/006-evaluacion-boletas/evidencias/`
- [x] T002 Instalar el único paquete nuevo con `docker compose exec app composer require barryvdh/laravel-dompdf` (PHP puro; justificado en plan.md, Complejidad) y confirmar que `composer.json`/`composer.lock` lo recogen y que la imagen Docker lo instala con `composer install` sin pasos manuales. **Antes de implementar la boleta (T034)**: revisar en el `Dockerfile` (y en la imagen de staging) que estén `mbstring`, `dom` y `gd` (`php -m`), añadir las que falten, y generar un **PDF mínimo en el contenedor** (`docker compose exec app php artisan tinker --execute=...` con `Pdf::loadHTML(...)->output()`, cuerpo que empieza con `%PDF` y acentos «áéíóúñ» legibles); registrar el resultado en el mensaje del commit

---

## Phase 2: Foundational (bloquea todas las historias)

- [x] T003 Migración `database/migrations/2026_11_13_000001_add_equivalentes_y_umbral_to_escalas_calificacion_table.php`: columnas `equivalentes` json **nullable** y `umbral_aprobacion` string(8) **nullable**; `up()` rellena por `codigo` solo donde estén en `null` (literal: `{"AD":4,"A":3,"B":2,"C":1}` y `B`; vigesimal: `null` y `11`); `down()` las elimina; ampliar `app/Models/EscalaCalificacion.php` (`fillable`, cast `equivalentes` a array) (R-03, data-model.md)
- [x] T004 Migración `2026_11_13_000002_create_criterios_evaluacion_table.php`: `seccion_id` FK restrict → secciones, `curso_id` FK restrict → cursos, `periodo_id` FK restrict → periodos_evaluacion, `nombre` string(60), `peso` decimal(5,2) (> 0 y ≤ 100), `orden` unsignedTinyInteger; **único `(seccion_id, curso_id, periodo_id, nombre)`**, índices `(seccion_id, curso_id, periodo_id, orden)`, `periodo_id` y `curso_id`; verificar con `migrate:fresh` (R-01)
- [x] T005 Migración `2026_11_13_000003_create_notas_table.php`: `criterio_id` FK **cascade** → criterios_evaluacion, `alumno_id` FK restrict → alumnos, `valor` string(8), `valor_numerico` decimal(5,2), `registrado_por` FK restrict → usuarios; **único `(criterio_id, alumno_id)`**, índice `alumno_id`; celda vacía = sin fila (R-02, R-11)
- [x] T006 Migración `2026_11_13_000004_create_cierres_periodo_table.php`: `seccion_id` FK restrict → secciones, `periodo_id` FK restrict → periodos_evaluacion, `cerrado_por` FK restrict → usuarios, `cerrado_en` timestamp; **único `(seccion_id, periodo_id)`**, índice `periodo_id`; fila presente = cerrado y publicado (R-05)
- [x] T007 [P] Modelos `app/Models/CriterioEvaluacion.php` (relaciones `seccion`, `curso`, `periodo`, `notas`; cast `peso` decimal:2; trait `RequierePertenencia`), `app/Models/Nota.php` (relaciones `criterio`, `alumno`, `registrador`; cast `valor_numerico` decimal:2; trait `RequierePertenencia`) y `app/Models/CierrePeriodo.php` (relaciones `seccion`, `periodo`, `cerrador`; cast `cerrado_en` datetime), y fábricas `database/factories/{CriterioEvaluacionFactory,NotaFactory,CierrePeriodoFactory}.php` con datos ficticios
- [x] T008 Ampliar `database/seeders/EscalasCalificacionSeeder.php` para sembrar `equivalentes` y `umbral_aprobacion` (comentario: «configuración de la institución, no regla del Currículo Nacional»); completa escalas existentes **solo si están en `null`** y no pisa cambios del Administrador (R-20); prueba `tests/Feature/Console/` de que corre vía `sge:sincronizar-base`, no duplica y no pisa valores existentes
- [x] T009 [P] `app/Evaluacion/CalculoPromedios.php` (clase pura, sin base de datos) y `tests/Unit/Evaluacion/CalculoPromediosTest.php` (SC-004): promedio ponderado de los criterios con nota con pesos renormalizados sobre esos criterios; sin ninguna nota → `null` («sin notas», nunca cero); anual = promedio simple de los promedios de periodo existentes; escala literal: promedia equivalentes y devuelve la letra del equivalente más cercano (empate → el mayor); vigesimal: 2 decimales; aprobado = promedio ≥ `umbral_aprobacion` (literal: equivalente de la letra del umbral); valida el valor contra la escala (literal: uno de `valores`; vigesimal: `minimo` ≤ n ≤ `maximo`, hasta 2 decimales, coma o punto) y devuelve `valor_numerico`; no nombra códigos de escala; casos con celdas vacías, un solo criterio, pesos 33,33/33,33/33,34, escala literal con un alumno en AD y otro en C (R-03, R-04, FR-007, FR-008)
- [x] T010 Ampliar `RolesYPermisosSeeder::PERMISOS` con los 7 permisos de `contracts/permisos-y-pertenencia.md` (`notas.ver_todas`, `notas.gestionar`, `notas.registrar_propia`, `notas.ver_propia`, `notas.cerrar_periodo`, `boletas.ver_todas` (Administrador y Director), `boletas.ver_propia`) y sus roles por defecto, conservando «asigna solo al crear el permiso»; prueba `tests/Feature/Evaluacion/PermisosEvaluacionSeederTest.php` (asignaciones, total 30, idempotencia, no pisa cambios del Administrador) (FR-018)
- [x] T011 [P] `app/Autorizacion/Pertenencia/Reglas/CriterioRegla.php` y `NotaRegla.php` según la tabla del contrato (`ver_todas` → todo; `registrar_propia` → pares (sección, curso) con asignación mía, `EXISTS` sobre `asignaciones_docentes`; `ver_propia` → alumnos propios o hijos (`MatriculaRegla::alumnosPropios`) **y** (sección, periodo) con fila en `cierres_periodo`; unión; fail-closed); registrarlas en `config/pertenencia.php`; pruebas `tests/Feature/Evaluacion/Pertenencia/{CriterioReglaTest,NotaReglaTest}.php` (cada combinación de permisos, periodo sin cierre invisible para la familia, usuario sin vínculos → vacío, regla no registrada → 403) (R-07)
- [x] T012 `app/Evaluacion/EvaluacionIncumplida.php` (excepción de dominio; mensajes con posición fila/columna o #id, nunca nombres ni documentos, R-17) y `app/Evaluacion/EvaluacionService.php` base con las reglas de escritura de R-08: `exigirAnioVigente(Seccion)`, `periodoCerrado(Seccion, PeriodoEvaluacion)`, `puedeEscribir(Usuario, Seccion, Curso)` (`notas.gestionar`, o `notas.registrar_propia` + asignación del par con docente que conserva `docencia.dictar`), `exigirEscritura(Usuario, Seccion, Curso, PeriodoEvaluacion)` (el Docente además exige periodo abierto; el Administrador no), `nominaActiva(Seccion)` (matrículas en estado `matriculado`, orden apellidos, nombres, id, R-09); prueba `tests/Feature/Evaluacion/EvaluacionServiceReglasTest.php` (permitido y denegado por cada combinación, asignación de docente sin `docencia.dictar` denegada, año no vigente, periodo ajeno al año)
- [x] T013 Grupos de rutas `/notas` y `/boletas` (middleware `auth` + `activo`) en `routes/web.php` **sin rutas hijas** y los ítems de `config/menu.php` (Registro de notas, Cierres de periodo, Mis notas, Reportes de notas) según `contracts/rutas-web.md`; cada historia registra sus rutas junto a su componente

**Checkpoint**: `migrate:fresh --seed` y la suite de los Módulos 1–4 siguen en verde.

---

## Phase 3: User Story 1 — Registrar notas de una sección (Priority: P1) 🎯 MVP

**Goal**: el Docente (o el Administrador) define criterios con peso y registra las notas de la nómina de un curso, sección y periodo.

**Independent Test**: sección con cinco alumnos; dos criterios (60/40); registrar notas, guardar, reabrir y corregir una.

- [x] T014 [P] [US1] `tests/Feature/Evaluacion/EsquemaCriteriosTest.php`: guardar el esquema de un (sección, curso, periodo) crea, actualiza y elimina criterios por id en una transacción; pesos que no suman **exactamente 100,00** (comparación en centésimas) se rechazan sin cambiar nada; peso ≤ 0 o > 100 rechazado; nombre vacío, > 60 caracteres o repetido en el esquema rechazado; esquema vacío rechazado; cambiar un peso recalcula el promedio sin tocar notas; eliminar un criterio borra sus notas (cascade) (R-01, R-11, escenario 1.4)
- [x] T015 [P] [US1] `tests/Feature/Evaluacion/GuardarNotasTest.php`: guardado crea una fila por celda llena y ninguna por celda vacía; reguardar reemplaza sin duplicar; vaciar una celda borra su fila; **concurrencia** (dos guardados → una fila por criterio y alumno, prevalece el último); **todo o nada** ante una celda fuera de escala (25 en vigesimal, «Z» en literal) con error de fila/columna sin nombres; alumno ajeno a la nómina activa o criterio ajeno al esquema rechazado; alumno retirado conserva notas y no aparece en tablas nuevas; matriculado a mitad de año aparece solo desde su matrícula; coma decimal «15,5» aceptada (R-02, R-09, R-10, FR-002–FR-004); **volumen SC-001**: 30 alumnos × 5 criterios en una sola operación
- [x] T016 [P] [US1] `tests/Feature/Evaluacion/Acceso/RegistroAccesoTest.php`: Docente con asignación en el par abre y guarda; Docente sin asignación → 403 al abrir y al guardar; Docente con periodo cerrado no guarda y el Administrador sí; Docente con año no vigente no guarda; Director, Padre y Alumno → 403; rol creado en la prueba con `notas.registrar_propia` funciona y sin permiso no (FR-005, FR-006, SC-003, escenarios 1.5 y 1.6)
- [x] T017 [P] [US1] `tests/Feature/Evaluacion/NotasControllerTest.php`: el guardado es un **POST clásico** a `notas.registro.guardar` con `@csrf` (sin token → 419); campos `notas[alumno_id][criterio_id]` y `periodo`; éxito → `302` de vuelta con aviso; celda inválida → `302` con errores por fila/columna y nada guardado; 403 sin permiso, par ajeno, periodo cerrado (Docente) o año no vigente; no usa `wire:model` en la tabla (R-18)
- [x] T018 [US1] Ampliar `app/Evaluacion/EvaluacionService.php`: `guardarEsquema(Seccion, Curso, PeriodoEvaluacion, array $criterios, Usuario)` (transacción; reemplazo por id; pesos que suman 100,00; `nombre` máx. 60; `peso` decimal(5,2) > 0 y ≤ 100) y `guardarNotas(Seccion, Curso, PeriodoEvaluacion, array $celdas, Usuario)` (autoriza con `exigirEscritura`; valida todas las celdas con `CalculoPromedios` y la escala de la institución; un solo `upsert` por lote de las llenas y borrado de las vaciadas; `registrado_por`; todo o nada; errores con posición, no nombres) (R-01, R-10, FR-001–FR-006)
- [x] T019 [US1] `app/Http/Controllers/NotasController.php` (`guardar`) y ruta `POST /notas/registro/{seccion}/{curso}` nombre `notas.registro.guardar` en `routes/web.php`: autoriza antes de tocar datos, llama a `guardarNotas`, responde `302` con aviso o errores por fila/columna (R-18, contracts/rutas-web.md)
- [x] T020 [US1] `app/Livewire/Evaluacion/Registro/Index.php` y vista: pares (sección, curso) del año vigente tomables por el usuario (`gestionar`: todos; `registrar_propia`: con asignación), paginados y con `with()`; `Registro/Show.php` y vista con `?periodo=` (por defecto el último periodo del año con `fecha_inicio` ≤ hoy; si ninguno, el primero): editor de criterios (agregar, quitar con confirmación, total de pesos en vivo, *Guardar criterios* con Livewire) y tabla alumno × criterio como **formulario clásico** con `@csrf` y promedio del periodo por alumno («sin notas» si vacío), columna «Boleta» por alumno solo con `boletas.ver_todas` (enlace a `boletas.descargar`), solo lectura sin botón cuando el periodo está cerrado y el usuario no tiene `notas.gestionar`; rutas `notas.registro.index` y `notas.registro.show` con el doble permiso en `mount()` y pertenencia antes de cargar relaciones
- [x] T021 [US1] Hacer pasar T014–T017 y revisar que el menú «Registro de notas» aparece solo con los permisos correctos

**Checkpoint**: US1 funciona sola; es el MVP.

---

## Phase 4: User Story 2 — Consultar notas y promedios (Priority: P1)

**Goal**: el Alumno ve sus notas y el Padre las de cada hijo, de periodos publicados, con promedios por periodo y anual.

**Independent Test**: Padre con dos hijos y notas en un periodo cerrado: ve a ambos y a ningún otro; Alumno solo las suyas.

- [x] T022 [P] [US2] `tests/Feature/Evaluacion/ConsultaNotasTest.php`: por curso y periodo, detalle por criterio y promedio; curso sin notas → «sin notas» y no cero; promedio anual solo con los periodos publicados existentes; periodo sin cierre invisible; escala literal muestra la letra; consultas sin N+1 (conteo de consultas constante al crecer los cursos) (FR-007, FR-010, escenarios 2.4 y 2.5)
- [x] T023 [P] [US2] `tests/Feature/Evaluacion/Acceso/PropiaAccesoTest.php`: Padre ve solo a sus hijos vinculados; Alumno solo lo suyo; dirección directa de un alumno ajeno → 403; Padre/Alumno no pueden modificar (el POST de guardado → 403); sin vínculos → lista vacía; Docente y Director → 403 en `/notas/mias`; rol creado en la prueba con `notas.ver_propia` funciona y sin permiso no
- [x] T024 [US2] `app/Evaluacion/ConsultaNotas.php`: `delAlumno(Alumno, ?PeriodoEvaluacion)` (cursos, criterios y notas con `with()`, usa `CalculoPromedios` y la escala de la institución, acotado a ≤ 15 cursos × ≤ 10 criterios) y `periodosPublicados(Alumno)`; respeta `Nota::visiblePara($usuario)` / `CriterioEvaluacion::visiblePara($usuario)` (R-12)
- [x] T025 [US2] `app/Livewire/Evaluacion/Propia/Index.php` y vista `/notas/mias` (`notas.propia.index`): hijos (o el propio alumno) con `limit(50)`, selector de periodo (solo publicados), detalle por curso y criterio, promedio por periodo y anual, «sin notas»; lista el enlace «Descargar boleta» que activa US4 (SC-002: Inicio → Mis notas → hijo → Descargar boleta); permiso en `mount()` y pertenencia antes de cargar relaciones
- [x] T026 [US2] Hacer pasar T022–T023 y revisar el menú «Mis notas»

**Checkpoint**: US1 y US2 funcionan juntas con periodos cerrados creados por fábrica; la interfaz de cierre llega en US3.

---

## Phase 5: User Story 3 — Cerrar y publicar un periodo (Priority: P2)

**Goal**: el Administrador cierra y reabre periodos por sección; el cierre bloquea al Docente y publica a la familia.

**Independent Test**: cerrar, comprobar que el Docente no edita y el Padre ve las notas; reabrir y comprobar lo contrario.

- [x] T027 [P] [US3] `tests/Feature/Evaluacion/CierrePeriodoTest.php`: cerrar crea una fila única con `cerrado_por` y `cerrado_en`; cerrar dos veces no duplica; reabrir elimina la fila; tras cerrar el Docente no guarda y la familia ve; tras reabrir, lo contrario; el Administrador sigue pudiendo corregir notas con el periodo cerrado; solo del año vigente y de un periodo del año de la sección (R-05, FR-009)
- [x] T028 [P] [US3] `tests/Feature/Evaluacion/Acceso/CierresAccesoTest.php`: solo `notas.cerrar_periodo` (Administrador) abre `/notas/cierres`, cierra y reabre; Docente, Padre, Alumno y Director → 403; rol creado en la prueba con y sin el permiso (SC-003, escenario 3.3)
- [x] T029 [US3] Ampliar `app/Evaluacion/EvaluacionService.php`: `cerrarPeriodo(Seccion, PeriodoEvaluacion, Usuario)` (idempotente, exige `notas.cerrar_periodo` y año vigente) y `reabrirPeriodo(...)` (elimina la fila)
- [x] T030 [US3] `app/Livewire/Evaluacion/Cierres/Index.php` y vista `/notas/cierres` (`notas.cierres.index`, `can:notas.cerrar_periodo`): secciones del año vigente × periodos con su estado, paginado y con `with()`, acciones cerrar/reabrir con confirmación; ítem de menú «Cierres de periodo»
- [x] T031 [US3] Hacer pasar T027–T028

**Checkpoint**: la publicación funciona de punta a punta (US1 + US2 + US3).

---

## Phase 6: User Story 4 — Generar la boleta de notas en PDF (Priority: P2)

**Goal**: boleta PDF de formato único, por periodo y anual, con resumen de asistencia; calificativo literal cuando la escala es literal.

**Independent Test**: con un periodo publicado, descargar la boleta de un alumno y comparar cursos, notas, promedios y asistencia con los registros.

- [x] T032 [P] [US4] `tests/Feature/Evaluacion/BoletaDatosTest.php`: `BoletaService::datos(Alumno, periodo|anual)` incluye institución, alumno, sección, cursos con notas por criterio y promedio por curso, y el resumen de asistencia de `ResumenAsistencia::delAlumno` para el rango del periodo (anual: el año); curso sin notas → «sin notas»; **con escala literal muestra el calificativo literal** por criterio, curso y anual, con vigesimal el número; anual incluye solo periodos publicados (la familia) o todos marcados (Admin/Director); sin asistencia → «sin datos»; **alumno trasladado de sección a mitad de año**: sus notas quedan en la sección original y la boleta muestra la sección vigente (spec, casos límite) (R-04, R-13, R-14, FR-011)
- [x] T033 [P] [US4] `tests/Feature/Evaluacion/Acceso/BoletaAccesoTest.php`: `boletas.ver_todas` (Administrador y Director) descarga la de cualquier alumno; `boletas.ver_propia` solo del propio alumno o hijo y solo periodos con cierre; ajeno, no publicado o anual sin periodos publicados → 403 para la familia; Administrador y Director reciben el PDF **marcado «BORRADOR»** si el periodo no está cerrado; **Docente → 403** aunque tenga asignación en la sección (mínimo privilegio, FR-012); **un rol creado en la prueba con solo `boletas.ver_propia` (sin `notas.ver_propia`) descarga la boleta completa de su hijo** y no la de un ajeno (F2: `BoletaService` no depende de `NotaRegla`); periodo inexistente → 404; la respuesta es `200 application/pdf` (cuerpo empieza con `%PDF`) con `Content-Disposition: attachment` y nombre `boleta-{alumno_id}-{periodo|anual}.pdf` sin datos personales; rol creado en la prueba con y sin permiso (FR-012, SC-003, escenarios 4.3–4.5); **SC-006**: la generación de una boleta de 12 cursos × 4 criterios tarda < 5 s
- [x] T034 [US4] `app/Evaluacion/BoletaService.php` (`datos(...)` y `pdf(...)` con `barryvdh/laravel-dompdf`; aplica el vínculo alumno propio/hijo y el cierre **directamente** con consultas propias, sin `NotaRegla`/`visiblePara`, e incluye todos los cursos (F2), A4, sin guardar en disco ni usar colas) y vista `resources/views/boletas/boleta.blade.php` de formato único: datos de la institución, alumno y sección, tabla de cursos × criterios con notas, promedio por curso y anual, calificativo literal si la escala es literal, resumen de asistencia, marca «BORRADOR» condicional; sin nombres de institución, curso ni periodo en la plantilla (Principio II)
- [x] T035 [US4] `app/Http/Controllers/BoletaController.php` (`descargar`) y ruta `GET /boletas/{estudiante}/{periodo}` nombre `boletas.descargar` en `routes/web.php`: **autoriza antes de generar** (`boletas.ver_todas` o `boletas.ver_propia` + vínculo + publicación; el Docente → 403, `{periodo}` = id o `anual`), 403/404 según el contrato, `download` del PDF; activa el enlace «Descargar boleta» de `Propia\Index` (periodo y anual) ; el enlace por alumno de Administrador/Director ya está en `Registro\Show` (T020) (R-13)
- [x] T036 [US4] Hacer pasar T032–T033

**Checkpoint**: US1–US4 completas; la boleta es descargable por familias y por Administrador/Docente.

---

## Phase 7: User Story 5 — Reporte de notas por sección (Priority: P3, recortable)

**Goal**: promedios por curso y por alumno, orden de mérito y desaprobados por curso, por sección y periodo.

**Independent Test**: sección con notas conocidas; el reporte coincide con el cálculo de la muestra.

- [ ] T037 [P] [US5] `tests/Feature/Evaluacion/ReporteSeccionTest.php`: promedios por curso, promedio general por alumno, orden de mérito (empates por apellidos, nombres, id; alumnos sin notas fuera del orden), desaprobados por curso contra `umbral_aprobacion`, escala literal, **el Docente ve solo los cursos que dicta** (su promedio general y orden de mérito se calculan sobre ellos; `NotaRegla` tal cual); sección sin notas → «sin datos», listado paginado y sin N+1 (R-15, SC-004)
- [ ] T038 [P] [US5] `tests/Feature/Evaluacion/Acceso/ReporteAccesoTest.php`: Administrador y Director ven cualquier sección sin controles de edición; Docente solo secciones con asignación y solo sus cursos; Padre, Alumno → 403; sección ajena → 403; quitar `notas.ver_todas` al Director corta su acceso; rol creado en la prueba con y sin permiso (FR-013)
- [ ] T039 [US5] Ampliar `app/Evaluacion/ConsultaNotas.php` con `reporteSeccion(Seccion, PeriodoEvaluacion, int $porPagina = 15)` (orden de mérito paginado; promedio por curso y desaprobados con `CalculoPromedios`; consulta única acotada a la sección, usando `Nota::visiblePara` / `CriterioEvaluacion::visiblePara` del usuario) y `app/Livewire/Evaluacion/Reportes/Index.php` con su vista (`notas.reportes.index`, doble permiso en `mount()`, selector de sección `limit(200)` solo de las visibles, `with()`, sin edición); ítem de menú «Reportes de notas»
- [ ] T040 [US5] Hacer pasar T037–T038

**Checkpoint**: las cinco historias funcionan.

---

## Phase 8: Polish, carga y cierre

- [x] T041 [P] `database/seeders/EvaluacionDemoSeeder.php`: criterios y notas **ficticios** para la matrícula de los seeders previos, con cierre de un periodo; rechaza `APP_ENV=production`; no crea cuentas; se llama desde `DatabaseSeeder` solo en local; prueba `tests/Feature/Seeders/EvaluacionDemoSeederTest.php` (genera datos, idempotente, rechaza producción) (R-16, FR-017)
- [x] T042 [P] `database/seeders/EvaluacionCargaSeeder.php`: solo `local`, `staging` y `testing` (rechaza producción, como `LoadTestSeeder`); amplía el rol `Carga` con `notas.ver_propia`, `boletas.ver_propia` y `matricula.ser_alumno` (necesario para vincularse a una ficha; comprobar en la prueba si `MatriculaRegla::alumnosPropios` exige además `matricula.ver_propia` y añadirlo si hace falta) y vincula `carga0001…1000` a alumnos ficticios con un periodo cerrado; crea 20 cuentas `cargadoc01…20` con un rol de carga con `notas.registrar_propia` y `docencia.dictar`, cada una con una sección ficticia de 30 alumnos × 5 criterios; idempotente; fuera de `DatabaseSeeder`; prueba `tests/Feature/Seeders/EvaluacionCargaSeederTest.php` (R-16, R-18, contracts/prueba-de-carga.md)
- [x] T043 [P] Pruebas transversales: `tests/Feature/Evaluacion/GenericidadTest.php` (una escala nueva creada solo con datos, con otros códigos, equivalentes y umbral, se promedia y se muestra en la boleta sin tocar código; ningún nombre de curso, periodo, criterio ni institución en `app/Evaluacion`, `app/Livewire/Evaluacion` ni las vistas, FR-019) y `tests/Feature/Evaluacion/RecorridoTest.php` (docente define criterios y guarda → Administrador cierra → Padre consulta y descarga la boleta → Administrador reabre → el Padre ya no ve); y SC-002: desde `/inicio` hay un enlace a «Mis notas» y en ella un enlace a la boleta (3 clics); confirmar que `SinCondicionalesDeRolTest` sigue en verde
- [x] T044 `tests/Load/evaluacion.js` según `contracts/prueba-de-carga.md`: reutiliza el login de `carga.js`; flujos de familias (~97 % `GET /notas/mias` etiqueta `consulta`, ~3 % `GET /boletas/{estudiante}/{periodo}` etiqueta `boleta`) y de 20 docentes (`GET /notas/registro/{seccion}/{curso}`, lee `_token` y nombres de campos, **`POST` del formulario clásico** con las 150 notas, etiqueta `notas`, un guardado cada ~60 s); perfiles `carga` y `diario`, `SMOKE=1` con 10 VU; umbrales por perfil de la tabla del contrato; comprobaciones de `302` y `application/pdf`
- [x] T045 [P] Documentación: `docs/evaluacion.md` (criterios, cierre y publicación, escala; **los equivalentes y el umbral son configuración de la institución, no regla del Currículo Nacional**; la boleta literal muestra el calificativo; cómo cambiarlos en datos), ampliar `docs/despliegue/prueba-de-carga.md` con los flujos y umbrales nuevos y la regla de activar/desactivar las cuentas `carga*`, y la sección del módulo en `README.md` (datos ficticios en staging, comandos de seeders)
- [x] T046 Ensayo local de la carga: `docker compose exec app php artisan db:seed --class=EvaluacionCargaSeeder` y `k6 run -e SMOKE=1 tests/Load/evaluacion.js`; corregir hasta que login, consulta, boleta y POST de notas pasen con `http_req_failed` < 1 %
- [x] T047 Pasada final local: `docker compose exec app php artisan test` completa, `docker compose exec app ./vendor/bin/pint --test`, `migrate:fresh --seed` desde cero; recorrer `quickstart.md` §1; actualizar `specs/006-evaluacion-boletas/checklists/requirements.md` si cambió el alcance
- [ ] T048 (manual) Integrar en `main`, etiquetar `v0.6.0`, desplegar a staging y hacer la **prueba de humo** de `quickstart.md` §2 (~10 min, Administrador y un rol limitado); registrar la evidencia en `specs/006-evaluacion-boletas/evidencias/staging.md` (versión, 7 pasos, permisos = 30)
- [ ] T049 (manual, 17–18/11, **sobre `v0.6.0` ya desplegada**) **Sesión única de carga k6** en staging según `docs/despliegue/prueba-de-carga.md` (perfil `carga`, luego `diario`): activar las cuentas `carga*`, sembrar `EvaluacionCargaSeeder`, ejecutar, copiar la evidencia a `specs/006-evaluacion-boletas/evidencias/` (resumen de k6, salida, `meta.json`, `meta-servidor.json`), desactivar las cuentas y documentar umbrales, picos y créditos (FR-016, SC-005, Constitución 2.2.0). Si la carga revela un problema, se corrige en `v0.6.1`

---

## Dependencies & Execution Order

- **Phase 1 → Phase 2 → historias**: Setup y Foundational bloquean todo. Dentro de Foundational: T003–T006 (migraciones) antes de T007; T007 antes de T011 y T012; T009 y T008 son independientes de las migraciones de notas; T013 al final.
- **US1 (MVP)** depende de Foundational. **US2** y **US5** dependen de US1 solo para tener datos reales (en pruebas se usan fábricas, por lo que pueden avanzar en paralelo tras Foundational). **US3** depende de T012 (reglas de escritura). **US4** depende de US2 (`ConsultaNotas`) y de la asistencia del Módulo 4.
- **Phase 8**: T041–T043 y T045 en paralelo tras US1–US4; T044 requiere T042; T046 requiere T044; T047 al final; T048 (integrar, `v0.6.0`, humo) y T049 (k6 sobre `v0.6.0`) son manuales y van en ese orden; un problema hallado en T049 se corrige en `v0.6.1`.

## Parallel Examples

- Foundational: T007, T009 y T011 en paralelo una vez hechas las migraciones.
- US1: T014–T017 (pruebas) en paralelo antes de T018–T020.
- US4: T032 y T033 en paralelo.
- Polish: T041, T042, T043 y T045 en paralelo.

## Implementation Strategy

1. **MVP**: Phases 1–3 (US1): criterios y registro de notas con guardado todo o nada.
2. Añadir US2 y US3 (consulta y publicación) y validar el recorrido docente → Administrador → familia.
3. Añadir US4 (boleta) y la sesión k6; US5 es **recortable** si hay retraso (orden de recorte del calendario: la boleta ya está reducida a un formato; la amplitud de Asistencia quedó atrás; no se toca el chatbot).
4. Cierre: integrar, `v0.6.0`, prueba de humo, y después la sesión k6 sobre `v0.6.0` (correcciones en `v0.6.1`).
