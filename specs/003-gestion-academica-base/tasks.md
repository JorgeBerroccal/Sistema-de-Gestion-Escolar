---

description: "Task list for 003-gestion-academica-base (Módulo 2, Gestión Académica Base)"
---

# Tasks: Gestión Académica Base

**Input**: Design documents from `/specs/003-gestion-academica-base/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Obligatorios (Constitución, Principio V): cada tarea de código lleva su prueba Pest en la misma historia; cada regla de autorización tiene prueba de acceso permitido y denegado, y cada regla de validación una de caso válido e inválido (FR-026). Las pruebas Feature usan MySQL (`sge_testing`, `RefreshDatabase`); las Unit no tocan la base. k6 no aplica (ningún endpoint crítico).

**Organization**: por historia de usuario (US1–US5 de spec.md). US1 y US2 son independientes entre sí; US3 necesita datos de ambas; US4 y US5 se apoyan en US3.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede hacer en paralelo (archivos distintos, sin dependencia pendiente)
- **[Story]**: US1 institución y año · US2 grados, secciones y cursos · US3 asignación docente · US4 acceso por rol · US5 desactivar en lugar de eliminar
- Comandos dentro de Docker: `docker compose exec app …`

## Path Conventions

Monolito Laravel 13 existente (ver plan.md): servicios en `app/Academico/`, modelos en `app/Models/`, componentes en `app/Livewire/Academico/`, vistas en `resources/views/livewire/academico/`, pruebas en `tests/Feature/Academico/` y `tests/Unit/Academico/`. Nombres de rol (Administrador, Director, Docente, Padre de Familia, Alumno) **no** pueden aparecer entre comillas en `app/`, `routes/`, `config/` ni `resources/views` (`SinCondicionalesDeRolTest`); en las pruebas se usan fábricas y permisos.

---

## Phase 1: Setup

- [x] T001 Comprobar la línea base: `docker compose exec app php artisan test` y `./vendor/bin/pint --test` en verde antes de empezar; anotar el resultado en el mensaje del primer commit
- [x] T002 [P] Crear `app/Academico/ReglaAcademicaIncumplida.php`: excepción de dominio (`RuntimeException`) con mensaje en español y propiedad opcional `cursoId` (curso que falló en una asignación múltiple), según `contracts/componentes-livewire.md`
- [x] T003 [P] Crear `app/Academico/TipoPeriodo.php`: enum respaldado `bimestre` | `trimestre` con `cantidadPeriodos()` (4 / 3) y `nombresSugeridos()` («I Bimestre»…, «I Trimestre»…)

---

## Phase 2: Foundational (bloquea todas las historias)

**⚠️ Ninguna historia puede cerrarse sin esto: permisos, sincronización de despliegue y escalas.**

- [x] T004 Ampliar `RolesYPermisosSeeder::PERMISOS` en `database/seeders/RolesYPermisosSeeder.php` con los 6 permisos de `contracts/permisos-y-pertenencia.md` y sus roles por defecto: `academico.ver` (Administrador, Director), `academico.gestionar` (Administrador), `asignaciones.ver_todas` (Administrador, Director), `asignaciones.ver_propias` (Docente), `asignaciones.gestionar` (Administrador), `docencia.dictar` (Docente). Mantener la regla «asigna solo al crear el permiso»
- [x] T005 Actualizar `tests/Unit/Seeders/RolesYPermisosDefinicionTest.php` y `tests/Feature/Seeders/RolesYPermisosSeederTest.php`: los permisos nuevos existen con el formato `Permiso::REGEX_IDENTIFICADOR`, Padre de Familia y Alumno no reciben ninguno, y volver a ejecutar el seeder no revierte un cambio hecho por el Administrador
- [x] T006 Crear migración `database/migrations/2026_10_24_000001_create_escalas_calificacion_table.php` (`id`; `codigo` varchar(20) único; `nombre` varchar(60) obligatorio; `valores` json obligatorio) y `app/Models/EscalaCalificacion.php` (`$table = 'escalas_calificacion'`, cast `valores` a array)
- [x] T007 Crear `database/seeders/EscalasCalificacionSeeder.php` (idempotente por `codigo`): `literal` con valores `AD, A, B, C` y `vigesimal` con `{minimo: 0, maximo: 20}`; agregarlo a `DatabaseSeeder` y probarlo en `tests/Feature/Seeders/EscalasCalificacionSeederTest.php` (dos filas; segunda ejecución no duplica)
- [x] T008 Crear `app/Console/Commands/SincronizarBase.php` (`sge:sincronizar-base {--force}`, usa `ConfirmableTrait` como `db:seed`): ejecuta una **lista fija de seeders base idempotentes** (`RolesYPermisosSeeder`, `EscalasCalificacionSeeder`), nunca `AdministradorInicialSeeder` ni `LoadTestSeeder`. Pruebas en `tests/Feature/Console/SincronizarBaseTest.php`: crea permisos y escalas; dos ejecuciones dan el mismo resultado y no revierten cambios del Administrador; **no crea ningún usuario** (ni el Administrador inicial ni los de carga) y la lista de seeders del comando no contiene esas dos clases; acepta `--force`; sin `--force` en producción pide confirmación
- [x] T009 Modificar `deploy/deploy.sh` (paso 5, justo después de `migrate`): `dc run --rm --no-deps app php artisan sge:sincronizar-base --force || fallo "La sincronización de roles y permisos falló."`; documentarlo en `docs/despliegue/desplegar-y-volver-atras.md` y comprobar con un ensayo local (`SGE_SKIP_PULL=1`) que el paso corre y que repetirlo no cambia nada y dejar escrito que los permisos eliminados o renombrados se limpian a mano (el comando solo crea; texto heredado de T086 de la especificación 002, trasladada aquí) (FR-027, SC-006, research R-10)

**Checkpoint**: permisos, escalas y despliegue listos; las historias pueden empezar.

---

## Phase 3: User Story 1 — Configurar la institución y el año escolar (P1) 🎯 MVP

**Goal**: ficha de institución con niveles y escala; año escolar con periodos y un único año vigente.

**Independent Test**: como Administrador, registrar la institución, crear el 2026 con cuatro bimestres y marcarlo vigente; crear el 2027, marcarlo vigente y verificar que el 2026 dejó de serlo.

### Tests (escribir primero, deben fallar)

- [x] T010 [P] [US1] `tests/Unit/Academico/TipoPeriodoTest.php`: 4 bimestres, 3 trimestres y nombres sugeridos
- [x] T011 [P] [US1] `tests/Feature/Academico/Institucion/EditarInstitucionTest.php`: guardar válido; rechazo sin nombre, sin código modular, sin UGEL/DRE, sin nivel atendido o con escala inexistente; con `academico.gestionar` permitido, con solo `academico.ver` guardar → 403, sin permiso la ruta → 403; no se puede crear una segunda ficha (`id` ≠ 1 rechazado por `CHECK`)
- [x] T012 [P] [US1] `tests/Feature/Academico/Niveles/NivelesTest.php`: crear/editar nivel con nombre y orden únicos; permitido/denegado por permiso
- [x] T013 [P] [US1] `tests/Feature/Academico/AniosEscolares/PeriodosTest.php`: válido (4 bimestres ordenados); inválidos: fuera del año (antes y después), inicio posterior al fin, superposición, **fin de uno = inicio del siguiente** (se rechaza), cantidad distinta de 4/3, cambiar fechas del año dejando un periodo fuera; días libres entre periodos permitidos; **cambio de tipo** (bimestre ↔ trimestre) permitido mientras ningún otro registro dependa de los periodos y regenera los periodos vacíos
- [x] T014 [P] [US1] `tests/Feature/Academico/AniosEscolares/AnioVigenteTest.php`: marcar segundo año deja uno solo vigente; año inactivo no se marca; nombre duplicado rechazado; nombre normalizado; **concurrencia, por separado como en el Módulo 1** (`ProteccionAdministracionTest`): (a) insertar un segundo `vigente = 1` lanza `QueryException` por el índice único; (b) con `DB::listen` se verifica que `marcarVigente()` emite `for update` sobre `anios_escolares` dentro de su transacción; no se simulan transacciones concurrentes reales (SC-002)

### Implementation

- [x] T015 [US1] Migración `database/migrations/2026_10_24_000002_create_instituciones_table.php`: `id` tinyint unsigned PK con `CHECK (id = 1)`; `nombre` varchar(150) obligatorio; `codigo_modular` varchar(20) obligatorio; `ugel_dre` varchar(150) obligatorio; `escala_calificacion_id` FK nullable con índice (la tabla de escalas ya existe por T006)
- [x] T016 [US1] Migración `2026_10_24_000003_create_niveles_tables.php` (**después** de instituciones): `niveles` (`nombre` varchar(60) obligatorio único; `orden` smallint unsigned obligatorio único; `activo` boolean por defecto true con índice) y pivote `institucion_nivel` (PK compuesta; `institucion_id` **tinyint unsigned**, del mismo tipo que `instituciones.id`, FK `cascadeOnDelete`; `nivel_id` FK `restrictOnDelete` con índice)
- [x] T017 [US1] Migración `2026_10_24_000004_create_anios_escolares_table.php`: `nombre` varchar(30) obligatorio único; `fecha_inicio`/`fecha_fin` date obligatorias; `tipo_periodo` varchar(15); `vigente` tinyint **nullable con índice único** (1 o NULL); `activo` boolean por defecto true con índice; índice en `fecha_inicio`. Migración `2026_10_24_000005_create_periodos_evaluacion_table.php`: `anio_escolar_id` FK `cascadeOnDelete`; `orden` tinyint unsigned; `nombre` varchar(40); `fecha_inicio`/`fecha_fin` date; único `(anio_escolar_id, orden)`
- [x] T018 [P] [US1] Modelos con relaciones, `$fillable`, casts y mutadores que recortan y colapsan espacios (`preg_replace('/\s+/u', ' ', trim())`): `app/Models/Nivel.php`, `Institucion.php` (con `actual()` = `firstOrNew(['id' => 1])`; su fábrica fija `id = 1`), `AnioEscolar.php` (cast `tipo_periodo` a `TipoPeriodo`, `activo` boolean, `esVigente()`), `PeriodoEvaluacion.php`; fábricas en `database/factories/` (`NivelFactory`, `AnioEscolarFactory`, `PeriodoEvaluacionFactory`, `InstitucionFactory`)
- [x] T019 [US1] `app/Academico/AnioEscolarService.php`: `marcarVigente()` (transacción, `lockForUpdate` de filas con `vigente = 1`, error `ReglaAcademicaIncumplida` si el año está inactivo) y `guardarPeriodos()` (valida el conjunto según research R-02, bloquea la fila del año, transaccional) y `cambiarTipoPeriodo()` (solo si ningún registro depende de los periodos; hoy ninguno)
- [x] T020 [P] [US1] `app/Livewire/Academico/Institucion/Edit.php` + vista `resources/views/livewire/academico/institucion/edit.blade.php`: `mount` exige `academico.ver`; `guardar` exige `academico.gestionar`; muestra la escala elegida (solo configuración) y los niveles atendidos (≥ 1)
- [x] T021 [P] [US1] `app/Livewire/Academico/Niveles/Index.php` + vista: listado paginado (15) con `crear`/`editar` (`academico.gestionar`); `desactivar`/`eliminar` se completan en US5
- [x] T022 [US1] `app/Livewire/Academico/AniosEscolares/Index.php` y `Form.php` + vistas: listado paginado con acción `marcarVigente` y mensaje de lista vacía (no error) cuando no hay resultados; edición simultánea: gana la última guardada sin dejar dos años vigentes (probado en T014); formulario de año con tipo de periodo y sus periodos (nombres propuestos, fechas por el Administrador); errores de regla mostrados con `addError('general', …)`
- [x] T023 [US1] Rutas del grupo `/academico` en `routes/web.php` (institución, niveles, años: ver `contracts/rutas-web.md`) con `can:academico.ver` / `can:academico.gestionar`, e ítems en `config/menu.php` (Institución, Años escolares) con `academico.ver`
- [x] T024 [US1] Ejecutar las pruebas T010–T014 hasta verde y `./vendor/bin/pint`

**Checkpoint**: US1 funcional y probada de forma independiente.

---

## Phase 4: User Story 2 — Definir grados, secciones y cursos (P1)

**Goal**: grados por nivel, secciones por grado y año, cursos con grados del mismo nivel.

**Independent Test**: crear grados de un nivel, secciones A y B de un grado en el año vigente y un curso asociado a todos los grados del nivel; verlos en sus listados.

**Dependencia con la Fase 7**: el escenario 6 de la historia (eliminar sin dependientes; ofrecer solo desactivar si los hay) se implementa y prueba en US5 (T051–T055). El resto de la historia es verificable por sí solo.

### Tests

- [ ] T025 [P] [US2] `tests/Feature/Academico/Grados/GradosTest.php`: crear con nombre y orden; rechazo de nombre u orden repetidos en el mismo nivel (también «1°» vs « 1° » y distinta capitalización); mismo nombre en otro nivel permitido; permitido/denegado por permiso
- [ ] T026 [P] [US2] `tests/Feature/Academico/Secciones/SeccionesTest.php`: crear A, B y C de un grado y año; cantidad distinta en otro grado; duplicado «A»/«a» en mismo grado y año rechazado (misma letra en otro año o grado permitida); permitido/denegado
- [ ] T027 [P] [US2] `tests/Feature/Academico/Cursos/CursosTest.php`: curso con nivel y varios grados del nivel; rechazo con grado de otro nivel; rechazo sin grados; nombre único por nivel; permitido/denegado

### Implementation

- [ ] T028 [US2] Migraciones: `2026_10_24_000006_create_grados_table.php` (`nivel_id` FK; `nombre` varchar(40); `orden` smallint unsigned; `activo` boolean con índice; únicos `(nivel_id, nombre)` y `(nivel_id, orden)`), `…000007_create_secciones_table.php` (`grado_id`, `anio_escolar_id` FK; `identificador` varchar(10); `activo`; único `(grado_id, anio_escolar_id, identificador)`; índice en `anio_escolar_id`), `…000008_create_cursos_tables.php` (`cursos`: `nivel_id` FK, `nombre` varchar(100), `activo`, único `(nivel_id, nombre)`; pivote `curso_grado` PK compuesta, `curso_id` `cascadeOnDelete`, `grado_id` `restrictOnDelete` con índice). Todas las FK con `restrictOnDelete` salvo lo indicado
- [ ] T029 [P] [US2] Modelos `app/Models/Grado.php`, `Seccion.php` (`$table = 'secciones'`; identificador en mayúsculas), `Curso.php` con relaciones, mutadores de normalización y fábricas (`GradoFactory`, `SeccionFactory`, `CursoFactory`)
- [ ] T030 [P] [US2] `app/Livewire/Academico/Grados/Index.php` + vista: listado paginado por nivel con alta y edición en línea (`academico.gestionar`); nivel activo requerido
- [ ] T031 [P] [US2] `app/Livewire/Academico/Secciones/Index.php` + vista: listado paginado con filtros año/nivel/grado y `with(['grado.nivel','anio'])`; `crear` y `crearVarias` (A, B, C…) por grado y año activos
- [ ] T032 [US2] `app/Livewire/Academico/Cursos/Index.php` y `Form.php` + vistas: selección de nivel y de grados **solo de ese nivel**, validación en servidor (no solo en la interfaz); la edición con asignaciones se endurece en T056
- [ ] T033 [US2] Rutas (`grados`, `secciones`, `cursos`) con permisos y menú (Grados, Secciones, Cursos); ejecutar T025–T027 hasta verde y `pint`

**Checkpoint**: US1 y US2 funcionan por separado.

---

## Phase 5: User Story 3 — Asignar docentes a cursos y secciones (P1)

**Goal**: asignar un docente a un curso en una sección del año vigente, uno a uno o varios cursos a la vez.

**Independent Test**: asignar tres cursos de una sección a un docente y dos cursos de otra a dos docentes distintos; ver cada asignación con curso, sección, año y docente.

### Tests

- [ ] T034 [P] [US3] `tests/Feature/Academico/Asignaciones/AsignarTest.php`: caso válido; rechazos: curso ya asignado en la sección, docente inactivo, usuario sin `docencia.dictar`, sección de año no vigente, sin año vigente, curso no asociado al grado, sección/curso inactivos; un docente con varias secciones permitido; reasignar cambia solo el docente; **`quitar`**: permitido con `asignaciones.gestionar` en el año vigente, rechazado en una sección de un año anterior (la asignación no cambia) y 403 sin el permiso
- [ ] T035 [P] [US3] `tests/Feature/Academico/Asignaciones/AsignarVariosTest.php`: tres cursos en una operación; si uno es inválido no se crea ninguno y el error nombra el curso; **concurrencia, por separado**: (a) insertar la misma pareja `(seccion_id, curso_id)` lanza `QueryException` por el índice único y el servicio la traduce a mensaje de validación, no 500; (b) con `DB::listen` se verifica el `for update` sobre la sección y el año vigente
- [ ] T036 [P] [US3] `tests/Feature/Academico/Asignaciones/DocentesElegiblesTest.php`: lista solo usuarios activos con `docencia.dictar`; un rol con ese permiso concedido por datos también aparece sin cambiar código
- [ ] T037 [P] [US3] `tests/Feature/Academico/Asignaciones/BloqueoAnioVigenteTest.php`: con `DB::listen`, `asignar()` y `marcarVigente()` bloquean la misma fila del año vigente (`for update`); tras cambiar el año vigente, asignar en una sección del año anterior se rechaza

### Implementation

- [ ] T038 [US3] Migración `2026_10_24_000009_create_asignaciones_docentes_table.php`: `seccion_id`, `curso_id`, `usuario_id` FK `restrictOnDelete`; único `(seccion_id, curso_id)`; índices `(usuario_id, seccion_id)` y `curso_id`
- [ ] T039 [US3] `app/Models/AsignacionDocente.php` (`$table = 'asignaciones_docentes'`, trait `RequierePertenencia`, relaciones `seccion`, `curso`, `docente` → `Usuario`, atributo `pendienteReasignar`) y `AsignacionDocenteFactory`
- [ ] T040 [US3] `app/Academico/AsignacionService.php` según `contracts/componentes-livewire.md`: `docentesElegibles()` (activo + `whereHas('rol.permisos', identificador = 'docencia.dictar')`, filtrada por búsqueda por prefijo), `asignar`, `asignarVarios` (todo o nada), `reasignar`, `quitar` (solo año vigente); transacción que bloquea sección y año vigente (research R-08); la violación del índice único se traduce a `ReglaAcademicaIncumplida`
- [ ] T041 [US3] `app/Autorizacion/Pertenencia/Reglas/AsignacionDocenteRegla.php` y registro en `config/pertenencia.php` según la tabla de `contracts/permisos-y-pertenencia.md` (por permisos, sin nombres de rol)
- [ ] T042 [US3] `app/Livewire/Academico/Asignaciones/Index.php` + vista (consulta con `visiblePara($usuario)` y `with(['docente','curso','seccion.grado.nivel','seccion.anio'])`, paginado 15, filtros, insignia «reasignar») y `Form.php` + vista (sección del año vigente, selección múltiple de cursos del grado, buscador de docentes elegibles, `reasignar`, `quitar`)
- [ ] T043 [US3] Rutas `asignaciones` (index, create) con `asignaciones.ver_todas` / `asignaciones.gestionar`; ítem de menú «Asignaciones»; ejecutar T034–T037 hasta verde y `pint`

**Checkpoint**: configuración completa utilizable por el Administrador.

---

## Phase 6: User Story 4 — Acceso por rol (P2)

**Goal**: Director solo consulta; Docente ve únicamente sus asignaciones; Padre de Familia y Alumno sin acceso.

**Independent Test**: con un usuario por rol, recorrer cada pantalla y acción del módulo.

### Tests

- [ ] T044 [P] [US4] `tests/Feature/Academico/Acceso/MatrizDeAccesoTest.php`: para cada ruta de `contracts/rutas-web.md` y cada rol por defecto (usuario por fábrica con el rol y permisos del seeder), respuesta esperada 200 o 403, y que la navegación solo ofrece lo permitido
- [ ] T045 [P] [US4] `tests/Feature/Academico/Acceso/DirectorSoloConsultaTest.php`: el Director ve todo y cada acción de modificación (`Livewire::test(...)->call(...)`) responde 403 sin cambiar datos
- [ ] T046 [P] [US4] `tests/Feature/Academico/Acceso/MisAsignacionesTest.php`: el Docente ve solo las suyas de **todos los años**; no ve las de otro docente; abrir `/academico/asignaciones/{id}` ajena → 403 sin datos; sin regla registrada en `config/pertenencia.php` → 403 (fail-closed); quitar el permiso `asignaciones.ver_propias` a su rol lo deja sin acceso sin tocar código
- [ ] T047 [P] [US4] `tests/Feature/Academico/Acceso/PermisosPorDatosTest.php`: quitar `academico.ver` a un rol desde la gestión del Módulo 1 corta su acceso (historia 4, escenario 7); Padre de Familia y Alumno → 403 en todas las rutas

### Implementation

- [ ] T048 [US4] `app/Livewire/Academico/Asignaciones/Show.php` + vista: `mount` acepta `asignaciones.ver_todas` o `asignaciones.ver_propias` y llama a `autorizarPertenencia()` **antes** de cargar relaciones
- [ ] T049 [US4] Ruta `academico.asignaciones.propias` («Mis asignaciones», `asignaciones.ver_propias`) y `academico.asignaciones.show`; ítem de menú «Mis asignaciones»; registrar `/academico/asignaciones/crear` **antes** de `/academico/asignaciones/{asignacion}` para que `crear` no se interprete como un id; en `Asignaciones\Index` comprobar en `mount` que el usuario tiene alguno de los dos permisos
- [ ] T050 [US4] Revisar que ningún botón de modificación se muestre sin su permiso (`@can`) en todas las vistas del módulo y que `tests/Feature/Autorizacion/SinCondicionalesDeRolTest.php` sigue en verde; ejecutar T044–T047 hasta verde

**Checkpoint**: seguridad del módulo comprobada (SC-003).

---

## Phase 7: User Story 5 — Desactivar en lugar de eliminar (P2)

**Goal**: lo que tiene asignaciones o dependientes no se elimina, se desactiva; el historial se conserva.

**Independent Test**: crear una asignación; intentar eliminar su curso, sección, grado y año; ninguno se elimina, desactivarlos funciona y la asignación sigue visible.

### Tests

- [ ] T051 [P] [US5] `tests/Feature/Academico/Desactivacion/EliminarTest.php`: año, grado, sección, curso y nivel sin dependientes se eliminan; con dependientes (asignaciones, secciones, cursos, grados) se impiden y se ofrece desactivar; la base rechaza el borrado aunque el código no lo impidiera (`restrictOnDelete`) (SC-004)
- [ ] T052 [P] [US5] `tests/Feature/Academico/Desactivacion/DesactivarTest.php`: desactivar y reactivar cada elemento; el año vigente no se desactiva ni elimina; nivel con grados activos no se desactiva; un elemento inactivo no se ofrece en selectores de asignación pero sus asignaciones siguen visibles y marcadas
- [ ] T053 [P] [US5] `tests/Feature/Academico/Desactivacion/DocenteDesactivadoTest.php`: al desactivar a un docente (Módulo 1) o quitarle `docencia.dictar` a su rol, sus asignaciones se conservan, aparecen «pendientes de reasignar» y no se le pueden crear nuevas
- [ ] T054 [P] [US5] `tests/Feature/Academico/Cursos/EditarCursoConAsignacionesTest.php` (research R-12): no se quita un grado con asignaciones de ese curso; no se cambia el nivel de un curso con grados o asignaciones

### Implementation

- [ ] T055 [US5] `tieneDependientes(): bool` en `Nivel`, `AnioEscolar`, `Grado`, `Seccion` y `Curso` según `data-model.md`, y acciones `desactivar`, `reactivar`, `eliminar` (con `academico.gestionar`) en los componentes `Index` de niveles, años, grados, secciones y cursos; la interfaz ofrece solo desactivar cuando hay dependientes
- [ ] T056 [US5] Reglas de estado: año vigente intocable, nivel con grados activos, selectores que filtran `activo = true`, y en `AsignacionService` el rechazo de asignar a elementos inactivos; endurecer `Cursos\Form` según R-12
- [ ] T057 [US5] Insignia «reasignar» y filtro «pendientes de reasignar» en `Asignaciones\Index`; `AsignacionService::quitar()` documenta con un comentario que los Módulos 4 y 5 deberán rechazar asignaciones con asistencia o notas; ejecutar T051–T054 hasta verde

**Checkpoint**: las cinco historias completas.

---

## Phase 8: Polish y verificación transversal

- [ ] T058 [P] `database/seeders/AcademicoDemoSeeder.php`: institución ficticia, niveles, grados, secciones, cursos, docentes y algunas asignaciones; **rechaza `APP_ENV=production`**; llamarlo desde `DatabaseSeeder` solo en `local`; prueba en `tests/Feature/Seeders/AcademicoDemoSeederTest.php` (carga y rechazo en producción)
- [ ] T059 [P] `tests/Feature/Rendimiento/ListadosAcademicosTest.php` (SC-005): con 200 secciones y 2000 asignaciones, cada listado y «Mis asignaciones» responden con un número constante de consultas (sin N+1) y tiempos por debajo de 2 s y 1,5 s; confirmar con `EXPLAIN` que usan índices
- [ ] T060 [P] `tests/Feature/Academico/GenericidadTest.php` (SC-007): cargar con fábricas otra institución (otros niveles, otra cantidad de grados y secciones) y operar sin cambiar código; más una prueba que busca en `app/`, `resources/views` y `config/` nombres de nivel, grado o curso de ejemplo y no encuentra ninguno
- [ ] T061 Actualizar `README.md` (módulo, `sge:sincronizar-base`, seeders y demos) y `docs/despliegue/primer-despliegue.md` (paso de sincronización); añadir los ítems nuevos a `.env.example` solo si surge alguna variable (no se prevé)
- [ ] T062 Recorrer `quickstart.md` §2 en local, **cronometrando** la configuración de una institución nueva (ficha, escala, año con periodos, grados, 3 secciones por grado, cursos y asignaciones de una sección; objetivo SC-001 < 30 min) y registrar tiempos y resultado en `specs/003-gestion-academica-base/evidencias/recorrido-local.md`
- [ ] T063 Ejecutar la suite completa, `pint --test` y `/speckit-analyze` final; corregir hallazgos
- [ ] T064 (manual) Etiquetar `v0.3.0`, desplegar con `deploy/deploy.sh` y ejecutar `quickstart.md` §3 con los usuarios de ejemplo de cada rol en staging; registrar en `evidencias/staging.md` (SC-006)

---

## Dependencies & Execution Order

- Fase 1 → Fase 2 (bloquea todo) → US1 y US2 (independientes, pueden ir en paralelo) → US3 (necesita años, secciones y cursos) → US4 y US5 (necesitan US3; entre sí son independientes) → Polish.
- Dentro de cada historia: pruebas (deben fallar) → migraciones → modelos → servicios → componentes → rutas y menú → verde.
- Las migraciones se ejecutan en orden numérico (`000001`…`000009`): escalas (T006) → instituciones (T015) → niveles y pivote (T016) → años (T017) → grados, secciones y cursos (T028) → asignaciones (T038). Verificar con `migrate:fresh` al terminar T016.

## Parallel Opportunities

- Fase 1: T002 y T003. Fase 2: T004–T005 con T006–T007; T008 después de T004 y T007.
- US1: T010–T014 en paralelo; luego T018, T020 y T021 en paralelo tras las migraciones.
- US2: T025–T027 en paralelo; T029–T031 en paralelo.
- US3: T034–T037 en paralelo. US4: T044–T047 en paralelo. US5: T051–T054 en paralelo.
- US4 y US5 pueden desarrollarse a la vez por distintas personas una vez cerrada US3.

## Implementation Strategy

- **MVP**: Fases 1–3 (US1) entregan institución y año vigente con periodos, lo que ya necesitan Asistencia y Evaluación.
- **Orden de entrega recomendado**: US1 → US2 → US3 (configuración completa utilizable) → US4 (seguridad) → US5 (protección del historial) → Polish y `v0.3.0`.
- Cada historia termina con sus pruebas en verde y `pint` limpio antes de pasar a la siguiente (Definición de Terminado de la Constitución).

## Traceability

| Requisito | Tareas |
|---|---|
| FR-001–FR-003 (incluye CRUD de niveles) | T006, T007, T011, T012, T015, T016, T018, T020, T021 |
| FR-004–FR-008 | T013, T014, T017, T018, T019, T022, T052, T056 |
| FR-009–FR-013 | T025–T033, T051, T052, T055, T056 |
| FR-014–FR-019 | T034–T043, T053, T057 |
| FR-020–FR-023 | T004, T005, T041, T044–T050 |
| FR-024 | T058, T060 |
| FR-025 | T059 |
| FR-026 | todas las tareas de pruebas |
| FR-027 | T008, T009, T064 |
| SC-001 | T062 · SC-002 | T014, T035, T037 · SC-003 | T044–T047 · SC-004 | T051 · SC-005 | T059 · SC-006 | T009, T064 · SC-007 | T060 |
