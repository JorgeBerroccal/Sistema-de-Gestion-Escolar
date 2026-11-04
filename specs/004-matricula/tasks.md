---

description: "Task list for 004-matricula (Módulo 3, Matrícula)"
---

# Tasks: Matrícula

**Input**: Design documents from `/specs/004-matricula/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Obligatorios (Constitución, Principio V): cada tarea de código lleva su prueba Pest; cada regla de autorización tiene prueba de acceso permitido y denegado, y cada regla de validación una de caso válido e inválido (FR-029). Sin k6 (Constitución 2.2.0).

**Organization**: por historia de usuario (US1–US4 de spec.md). US1 es el MVP; US2 y US3 se apoyan en US1; US4 en US1 y US3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede hacer en paralelo (archivos distintos, sin dependencia pendiente)
- **[Story]**: US1 alumnos y matrícula · US2 estados e historial · US3 vínculos · US4 consulta por rol
- Comandos dentro de Docker: `docker compose exec app …`

## Path Conventions

Monolito Laravel 13 (ver plan.md): servicios en `app/Matricula/`, modelos en `app/Models/`, componentes en `app/Livewire/Matricula/`, vistas en `resources/views/livewire/matricula/`, pruebas en `tests/Feature/Matricula/` y `tests/Unit/Matricula/`. **Datos de ejemplo siempre ficticios** (FR-025).

---

## Phase 1: Setup

- [ ] T001 Crear los directorios `app/Matricula/`, `app/Livewire/Matricula/{Alumnos,Nominas,MisAlumnos}`, `resources/views/livewire/matricula/`, `tests/Feature/Matricula/`, `tests/Unit/Matricula/` y `specs/004-matricula/evidencias/`
- [ ] T002 [P] Enums de dominio en `app/Matricula/EstadoMatricula.php` (`matriculado`, `retirado`, `trasladado`), `app/Matricula/Parentesco.php` (`madre`, `padre`, `tutor`, `otro`) y `app/Matricula/Sexo.php` (`F`, `M`, con etiqueta «femenino»/«masculino»); prueba unitaria `tests/Unit/Matricula/EnumsTest.php`

---

## Phase 2: Foundational (bloquea todas las historias)

- [ ] T003 Migración `database/migrations/2026_11_05_000001_create_tipos_documento_table.php`: `codigo` string(10) único, `nombre` string(60), `longitud_min`/`longitud_max` tinyint unsigned, `solo_digitos` boolean, `activo` boolean por defecto sí (data-model.md)
- [ ] T004 Migración `2026_11_05_000002_create_alumnos_table.php`: `nombres`/`apellidos` string(100) con índice `(apellidos, nombres)`; `tipo_documento_id` FK restrict; `numero_documento` string(20) con índice; **único `(tipo_documento_id, numero_documento)`**; `fecha_nacimiento` date; `sexo` string(1); `usuario_id` FK nullable → usuarios, **único**, restrict; `activo` boolean por defecto sí
- [ ] T005 Migración `2026_11_05_000003_create_alumno_apoderado_table.php`: `alumno_id` FK cascade, `usuario_id` FK restrict con índice, `parentesco` string(20), **único `(alumno_id, usuario_id)`**
- [ ] T006 Migración `2026_11_05_000004_create_matriculas_table.php`: `alumno_id` FK restrict, `seccion_id` FK restrict, `anio_id` FK → anios_escolares, `fecha_matricula` date, `estado` string(12), `fecha_estado` date nullable, `motivo` string(255) nullable, **columna generada almacenada `anio_activa_id` = `IF(estado = 'matriculado', anio_id, NULL)`**, único `(alumno_id, anio_activa_id)`, índices `(seccion_id, estado)` y `(alumno_id, anio_id)`; verificar con `migrate:fresh` (R-01, R-02)
- [ ] T007 [P] Modelos `app/Models/TipoDocumento.php` y `app/Models/Alumno.php` (relaciones `tipoDocumento`, `usuario`, `apoderados` con parentesco en el pivote, `matriculas`; `numero_documento` normalizado con `trim` y mayúsculas; accesor `nombreCompleto`) y fábricas `database/factories/{TipoDocumentoFactory,AlumnoFactory}.php` **con datos ficticios** (nombres inventados, DNI del rango `9000xxxx`)
- [ ] T008 [P] Modelo `app/Models/Matricula.php` (tabla `matriculas`; relaciones `alumno`, `seccion`, `anio`; cast de `estado` a `EstadoMatricula`; scope `activas`) y fábrica `database/factories/MatriculaFactory.php`
- [ ] T009 `database/seeders/TiposDocumentoSeeder.php` idempotente (`updateOrCreate` por código): DNI 8–8 solo dígitos; CE 9–12 alfanumérico; OTRO 4–20 alfanumérico (R-03); añadirlo a `SincronizarBase::SEEDERS` en `app/Console/Commands/SincronizarBase.php`; ampliar `tests/Feature/Console/` con la prueba de que corre y no duplica
- [ ] T010 Ampliar `RolesYPermisosSeeder::PERMISOS` con los 6 permisos de `contracts/permisos-y-pertenencia.md` y sus roles por defecto (`matricula.ver_todas`, `matricula.gestionar`, `matricula.ver_nomina_propia`, `matricula.ver_propia`, `matricula.ser_alumno`, `matricula.ser_apoderado`), manteniendo la regla «asigna solo al crear el permiso»; prueba `tests/Feature/Matricula/PermisosMatriculaSeederTest.php` (asignaciones por defecto, idempotencia, no pisa cambios del Administrador)
- [ ] T011 [P] `app/Autorizacion/Pertenencia/Reglas/AlumnoRegla.php` y `MatriculaRegla.php` según la tabla del contrato (unión de condiciones, fail-closed, solo por permisos); usar `RequierePertenencia` en `Alumno` y `Matricula`; registrarlas en `config/pertenencia.php`; pruebas `tests/Feature/Matricula/Pertenencia/{AlumnoReglaTest,MatriculaReglaTest}.php` (cada combinación de permisos, usuario sin vínculos → vacío, regla no registrada → 403)
- [ ] T012 Rutas del grupo `/matricula` en `routes/web.php` y menú en `config/menu.php` (Alumnos y Nóminas con `matricula.ver_todas`; Mis secciones con `matricula.ver_nomina_propia`; Mis alumnos con `matricula.ver_propia`) según `contracts/rutas-web.md`; los componentes se crean en cada historia (las rutas sin componente se añaden con su historia)
- [ ] T013 [P] Ampliar la regla de eliminación de secciones del Módulo 2 en `app/Livewire/Academico/Secciones/Index.php`: una sección con matrículas solo se desactiva (FR-017); prueba `tests/Feature/Matricula/SeccionConMatriculasTest.php`

**Checkpoint**: `migrate:fresh --seed` y la suite del Módulo 1–2 siguen en verde.

---

## Phase 3: User Story 1 — Registrar alumnos y matricularlos (P1) 🎯 MVP

**Goal**: el Administrador registra alumnos y los matricula de forma individual y masiva.

**Independent Test**: registrar tres alumnos, matricular uno y dos en forma masiva; ver las nóminas.

- [ ] T014 [P] [US1] `tests/Feature/Matricula/Alumnos/AlumnoServiceTest.php`: alta válida; rechazo por DNI ≠ 8 dígitos, por documento repetido (mismo tipo y número) y aceptación del mismo número con otro tipo; fecha futura; nombres vacíos; espacios normalizados; violación del único por concurrencia traducida a mensaje; eliminar sin matrículas, desactivar con matrículas
- [ ] T015 [P] [US1] `tests/Feature/Matricula/Matriculas/MatricularTest.php` (escenarios 5–8 de la historia): matrícula válida; alumno con activa en el año → rechazo; alumno o sección desactivados, sección de año no vigente, sin año vigente → rechazo; **concurrencia** de dos matrículas del mismo alumno y año → una sola; masiva todo o nada con un inelegible
- [ ] T016 [US1] `app/Matricula/AlumnoService.php`: `crear`, `actualizar`, `desactivar`, `reactivar`, `eliminar` con las reglas de FR-001–FR-006 (formato del documento leído de `tipos_documento`; transacción; mensajes sin datos personales, R-11)
- [ ] T017 [US1] `app/Matricula/MatriculaService.php`: `matricular` (bloquea al alumno con `lockForUpdate`, valida activo/sección activa/año vigente/sin activa, fija `anio_id`, fecha y estado) y `matricularVarios` (todo o nada, identifica el motivo por posición, R-08, R-11)
- [ ] T018 [P] [US1] `tests/Feature/Matricula/Acceso/GestionAlumnosTest.php`: con `matricula.gestionar` crear/editar/desactivar permitido; con solo `matricula.ver_todas` → 403; rol sin permiso → 403 (rutas y acciones Livewire)
- [ ] T019 [US1] `app/Livewire/Matricula/Alumnos/Index.php` + vista: listado paginado (15) con búsqueda por nombre, apellido o documento y filtro de activos; `mount` exige `matricula.ver_todas`; acciones exigen `matricula.gestionar`; `with()` de tipo de documento (R-12)
- [ ] T020 [US1] `app/Livewire/Matricula/Alumnos/Form.php` + vista (crear/editar) con `matricula.gestionar`, tipo de documento desde `tipos_documento` activos y errores de validación en español
- [ ] T021 [US1] `app/Livewire/Matricula/Nominas/Index.php` + vista (secciones del año vigente con filtro de año y conteo de matriculados) y `Nominas/Show.php` + vista (nómina paginada con búsqueda y filtro de estado; con `matricula.gestionar`, matrícula individual y masiva eligiendo alumnos elegibles y mostrando el rechazo todo o nada); registrar las rutas de nóminas y alumnos
- [ ] T022 [P] [US1] `tests/Feature/Matricula/Nominas/NominaTest.php`: nómina por sección y año con paginación, búsqueda sin tildes ni mayúsculas, filtro de estado, lista vacía sin coincidencias y mensaje de «sin año vigente»

**Checkpoint**: US1 funciona sola con el Administrador.

---

## Phase 4: User Story 2 — Retirar, trasladar y consultar historial (P1)

**Goal**: cambiar el estado de una matrícula, rematricular y ver el historial.

**Independent Test**: matricular, retirar con motivo, ver el historial y matricular de nuevo.

- [ ] T023 [P] [US2] `tests/Feature/Matricula/Matriculas/CambiarEstadoTest.php`: retirar/trasladar con fecha y motivo; fecha anterior a la matrícula → rechazo; corregir a «matriculado» solo sin otra activa en el año; rematricular tras retiro (queda el historial); una matrícula nunca se elimina; desactivar alumno con activa → rechazo; eliminar alumno con matrículas → rechazo (SC-004)
- [ ] T024 [US2] Ampliar `app/Matricula/MatriculaService.php` con `retirar`, `trasladar` y `corregir` (bloqueo del alumno, validación de fechas y de la activa del año) y bloquear en `AlumnoService::desactivar` al alumno con matrícula activa (FR-014, FR-015, FR-005)
- [ ] T025 [US2] `app/Livewire/Matricula/Alumnos/Show.php` + vista: datos del alumno, historial de matrículas (año, grado, sección, estado, fechas, motivo) y, con `matricula.gestionar`, acciones matricular, retirar, trasladar, corregir, desactivar/reactivar y eliminar; `mount` exige `ver_todas` o `ver_propia` y llama a `autorizarPertenencia()` antes de cargar relaciones (FR-019)
- [ ] T026 [P] [US2] `tests/Feature/Matricula/Alumnos/FichaAlumnoTest.php`: historial completo de varios años; acciones visibles solo con `matricula.gestionar`

---

## Phase 5: User Story 3 — Vincular usuario y apoderados (P2)

**Goal**: vincular el usuario del alumno y uno o más apoderados con parentesco.

**Independent Test**: vincular un Alumno y dos Padres a una ficha; un padre a un segundo hijo.

- [ ] T027 [P] [US3] `tests/Feature/Matricula/Vinculos/VinculosTest.php` (escenarios 1–6): vincular usuario con `matricula.ser_alumno` y activo; usuario ya vinculado → rechazo; sin el permiso o inactivo → rechazo; apoderado con parentesco, varios hijos, sin repetir el mismo apoderado en el alumno; sin `matricula.ser_apoderado` (por ejemplo un Docente) → rechazo; quitar un vínculo conserva los demás y las matrículas
- [ ] T028 [US3] Ampliar `app/Matricula/AlumnoService.php` con `vincularUsuario`, `quitarUsuario`, `agregarApoderado`, `quitarApoderado`; elegibilidad por permisos marcadores (`matricula.ser_alumno`, `matricula.ser_apoderado`), usuario activo (FR-007–FR-010)
- [ ] T029 [US3] Ampliar `Alumnos/Show.php` y su vista con la sección de vínculos y apoderados (selectores de usuarios elegibles, parentesco, quitar) bajo `matricula.gestionar`
- [ ] T030 [P] [US3] `tests/Feature/Matricula/Vinculos/VinculoInactivoTest.php`: un usuario vinculado que se desactiva o pierde el permiso conserva el vínculo pero deja de ver datos (borde de la spec)

---

## Phase 6: User Story 4 — Consulta según el rol (P2)

**Goal**: cada rol ve solo lo suyo.

**Independent Test**: iniciar sesión con cada rol e intentar ver y modificar lo ajeno.

- [ ] T031 [P] [US4] `tests/Feature/Matricula/Acceso/PorRolTest.php` (SC-003): Director ve todo y no ve acciones ni ejecuta modificaciones (403); Docente solo nóminas de sus secciones con nombres, apellidos y documento, 403 en la nómina de otra sección y en la ficha de un alumno; Padre solo sus hijos, 403 en alumno ajeno; Alumno solo su ficha, sin nóminas ni compañeros; usuario sin vínculos → lista vacía; Padre y Alumno 403 en `/matricula/alumnos` y `/matricula/nominas`
- [ ] T032 [US4] `app/Livewire/Matricula/MisAlumnos/Index.php` + vista: alumnos del Padre (hijos) o del Alumno (propio) vía `Alumno::visiblePara()`, con enlace a la ficha de solo lectura; `mount` exige `matricula.ver_propia`
- [ ] T033 [US4] Ajustar `Nominas/Index.php` y `Nominas/Show.php` para el Docente: secciones con asignación suya (`MatriculaRegla`), columnas limitadas a nombres, apellidos, documento, estado y fecha, sin enlace a la ficha; comprobar pertenencia antes de cargar relaciones (R-13, FR-022)
- [ ] T034 [P] [US4] `tests/Feature/Matricula/Acceso/PermisosPorDatosTest.php`: quitar `matricula.ver_todas` al Director desde la gestión de roles corta su acceso sin cambiar código (escenario 8)
- [ ] T035 [P] [US4] `tests/Feature/Matricula/Privacidad/DatosPersonalesTest.php` (FR-026): los mensajes de error de los servicios y el archivo de registro no contienen nombres ni documentos tras operaciones inválidas; la búsqueda de quien no tiene acceso no devuelve datos

---

## Phase 7: Polish y verificación transversal

- [ ] T036 [P] `database/seeders/MatriculaDemoSeeder.php`: alumnos y apoderados **ficticios** (nombres inventados, DNI del rango `9000xxxx`, correos `@example.test`), vínculos y matrículas sobre las secciones de `AcademicoDemoSeeder`; **rechaza `APP_ENV=production`**; llamarlo desde `DatabaseSeeder` solo en `local`; prueba `tests/Feature/Seeders/MatriculaDemoSeederTest.php` (carga, vínculos con los usuarios demo y rechazo en producción) (FR-025, R-10)
- [ ] T037 [P] `tests/Feature/Rendimiento/ListadosMatriculaTest.php` (SC-005): con 2000 alumnos y 200 secciones, listado, nómina y búsqueda con número de consultas constante (sin N+1) y por debajo de 2 s; confirmar con `EXPLAIN` que usan índices
- [ ] T038 [P] `tests/Feature/Matricula/GenericidadTest.php` (SC-007): buscar en `app/`, `resources/views` y `config/` nombres de nivel, grado, sección o institución de ejemplo y no encontrar ninguno; operar con una institución distinta sin cambiar código
- [ ] T039 [P] `tests/Feature/Matricula/RecorridoQuickstartTest.php`: recorrido automatizado del quickstart §2 con los componentes reales (alta, rechazos de DNI y repetido, matrícula individual y masiva, retiro, vínculo, 403 de rol limitado)
- [ ] T040 Actualizar `README.md` (módulo 3, seeder de ejemplo ficticio, regla Ley 29733) y `docs/despliegue/primer-despliegue.md` / `desplegar-y-volver-atras.md` (tipos de documento en la sincronización de datos base y carga de alumnos ficticios en staging)
- [ ] T041 Ejecutar la suite completa, `pint --test` y `/speckit-analyze` final; corregir hallazgos
- [ ] T042 (manual) Etiquetar `v0.4.0`, desplegar con `deploy/deploy.sh`, cargar `MatriculaDemoSeeder` (ficticio) y ejecutar la **prueba de humo de ~10 min** de `quickstart.md` §2–§3 con el Administrador y un rol limitado; repetir el despliegue y comprobar que no duplica permisos ni tipos; registrar en `evidencias/staging.md` (SC-006)

---

## Dependencies & Execution Order

- Fase 1 → Fase 2 (bloquea todo) → US1 → US2 y US3 (independientes entre sí, ambas necesitan US1 y `Alumnos/Show` de T025 para T029) → US4 (necesita US1 y US3) → Polish.
- Dentro de cada historia: pruebas (deben fallar) → servicios → componentes → rutas → verde.
- Migraciones en orden `000001`…`000004` (tipos → alumnos → vínculos → matrículas); verificar con `migrate:fresh` al terminar T006.
- T029 depende de T025 (mismo componente `Show`).

## Parallel Opportunities

- Fase 2: T007, T008, T011 y T013 en paralelo tras las migraciones.
- US1: T014, T015 y T018 (pruebas) en paralelo; T019 y T020 en paralelo tras T016.
- US2/US3 en paralelo entre sí (salvo T029 tras T025).
- Polish: T036–T039 en paralelo.

## Implementation Strategy

- **MVP**: Fases 1–3 (US1). Con US2 la nómina refleja la realidad; US3 y US4 completan el acceso de las familias.
- **Orden de recorte si hay retraso** (calendario aprobado): reducir la amplitud de la matrícula masiva y el filtro de año en nóminas (T021) antes que reglas de seguridad; nunca recortar T011, T031, T035 ni la regla de datos ficticios (T036).
- Objetivo de calendario: despliegue `v0.4.0` el lunes 9/11.
