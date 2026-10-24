# Research: Gestión Académica Base

**Feature**: `003-gestion-academica-base` | **Date**: 2026-10-24

No quedaban `NEEDS CLARIFICATION` en la spec; esta investigación fija las decisiones técnicas que la spec dejó
al plan y contrasta la spec con el código real del Módulo 1.

## R-01 — Un solo año vigente (FR-005, SC-002)

- **Decision**: columna `vigente` `TINYINT NULL` con índice **único**; vale `1` solo en el año vigente y `NULL`
  en los demás. `AnioEscolarService::marcarVigente()` abre una transacción, bloquea con `lockForUpdate` las
  filas de `anios_escolares` con `vigente = 1`, pone `NULL` a las demás y `1` al elegido.
- **Rationale**: MySQL no tiene índices únicos parciales, pero permite varios `NULL` en un índice único: la base
  de datos misma impide dos vigentes aunque falle la lógica de aplicación. El bloqueo serializa solicitudes
  simultáneas; la prueba de SC-002 lo verifica.
- **Alternatives**: columna generada con índice único (más compleja, mismo efecto); tabla de configuración con
  `anio_vigente_id` (otra tabla y otra FK sin ventaja); solo validar en código (permite carrera).

## R-02 — Periodos sin superposición (FR-006, FR-007)

- **Decision**: `AnioEscolarService::guardarPeriodos()` valida el conjunto completo en memoria (cada fecha dentro
  del año, inicio < fin de cada periodo, orden consecutivo por `orden`, y `fin_n < inicio_n+1` estricto, porque
  cada día pertenece a un solo periodo) y guarda en transacción bloqueando la fila del año
  (`lockForUpdate`). Cantidad exigida: 4 bimestres o 3 trimestres (enum `TipoPeriodo`).
- **Rationale**: MySQL no soporta restricciones de exclusión por rango; con 3–4 filas por año validar el
  conjunto en la transacción es lo más simple y a prueba de carrera (el bloqueo del año serializa a dos
  administradores).
- **Alternatives**: triggers (mantenimiento oculto); validar periodo a periodo en la base (N consultas).

## R-03 — Niveles y escalas como datos (FR-002, FR-003, FR-024)

- **Decision**: tabla `niveles` con CRUD mínimo en la interfaz (nombre, orden, activo) y tabla pivote
  `institucion_nivel`. Escala en tabla `escalas_calificacion` (codigo, nombre, tipo, valores) con las dos
  filas fijas que carga `EscalasCalificacionSeeder`, que es **parte de la carga base** (`DatabaseSeeder` y
  `sge:sincronizar-base`), no de ejemplo; la institución guarda `escala_calificacion_id`.
- **Rationale**: producción no carga seeders de ejemplo; si los niveles solo vinieran de un seeder de ejemplo,
  una instalación real no podría configurarse. Las escalas son una opción de producto cerrada (la spec exige
  exactamente dos) que vive en datos para cumplir FR-024 sin escribir sus nombres en código.
- **Alternatives**: enum PHP de escalas (viola FR-024); niveles solo por seeder (instalación real inoperante);
  escalas personalizables (fuera de alcance según Assumptions).

## R-04 — Ficha única de la institución (FR-001)

- **Decision**: tabla `instituciones` con `id` fijo en `1` mediante `CHECK (id = 1)` (MySQL 8.0.16+ lo hace
  cumplir). `Institucion::actual()` hace `firstOrNew(['id' => 1])`.
- **Rationale**: garantiza «no existe más de una ficha» en la base y evita lógica de unicidad.
- **Alternatives**: columna `singleton` única (equivalente pero menos legible); solo validar en código.

## R-05 — Elegibilidad de docente sin nombrar el rol (FR-015, Assumptions)

- **Decision**: permiso marcador `docencia.dictar` («Puede recibir asignaciones como docente»). Es elegible
  el usuario con `activo = true` cuyo rol tiene ese permiso (`whereHas('rol.permisos', …)`). El seeder lo
  asigna por defecto al rol Docente; el Administrador puede dárselo a otro rol solo con datos.
- **Rationale**: reutiliza las tablas RBAC del Módulo 1 (sin columna nueva ni migración sobre `roles`),
  cumple el Principio III y `SinCondicionalesDeRolTest`. Es una comprobación de elegibilidad, no de acceso;
  se documenta así.
- **Alternatives**: columna `roles.es_docente` (cambia una tabla del Módulo 1 y duplica el concepto de
  permiso); comparar el nombre del rol (prohibido).

## R-06 — «Marcada para reasignar» (FR-019)

- **Decision**: es un estado **derivado**, no una columna: una asignación está pendiente de reasignar si su
  docente está inactivo o su rol ya no tiene `docencia.dictar`. Se calcula con la misma consulta de
  elegibilidad (R-05) y se muestra como insignia en el listado.
- **Rationale**: no hay que sincronizar nada cuando el Módulo 1 desactiva o cambia de rol a un usuario; nunca
  queda un estado desfasado.
- **Alternatives**: columna `reasignar` mantenida por eventos del modelo `Usuario` (acopla Módulo 1 con 2 y
  puede desfasarse).

## R-07 — Pertenencia de «Mis asignaciones» (FR-022, FR-023)

- **Decision**: `AsignacionDocente` usa `RequierePertenencia`; `AsignacionDocenteRegla` se registra en
  `config/pertenencia.php`. `aplicar()`: con `asignaciones.ver_todas` no filtra; con `asignaciones.ver_propias`
  filtra `usuario_id = $usuario->id`; sin ninguno devuelve `whereRaw('1 = 0')`. `permite()` replica la lógica.
  Un único componente `Asignaciones\Index` sirve «Asignaciones» (todas) y «Mis asignaciones» consultando
  siempre `AsignacionDocente::query()->visiblePara($usuario)`.
- **Rationale**: es exactamente el mecanismo del Módulo 1 (contrato `pertenencia.md`); quitar la regla de
  `config/pertenencia.php` produce 403 (fail-closed), lo que cubre el escenario 6 de la historia 4.
- **Alternatives**: filtrar `where usuario_id` a mano en el componente (omite el fail-closed y la reutilización
  del Módulo 6).

## R-08 — Un docente por curso y sección; todo o nada (FR-017, FR-018)

- **Decision**: índice único `(seccion_id, curso_id)` en `asignaciones_docentes`. `AsignacionService`
  valida en una transacción que bloquea la sección (`lockForUpdate` sobre `secciones` y sobre el año vigente):
  año vigente y activo, sección y curso activos, curso asociado al grado, docente elegible. La asignación
  múltiple crea todas dentro de la misma transacción y, ante la primera inválida, lanza una excepción de
  dominio que hace rollback e informa cuál falló. La violación del índice único se traduce a un error de
  validación («ya tiene docente»).
- **Rationale**: la comprobación de «año vigente» compite con `marcarVigente()`; bloquear la fila del año
  vigente en ambas operaciones evita asignar a un año que dejó de serlo en el mismo instante.
- **Alternatives**: validar sin bloqueo (carrera con el cambio de año vigente).

## R-09 — Eliminar o desactivar (FR-012, FR-013, SC-004)

- **Decision**: cada modelo expone `tieneDependientes(): bool` (existencia de secciones, cursos, asignaciones,
  periodos con datos, etc., según la tabla de data-model). Eliminar físicamente solo si es `false`; si no, la
  interfaz ofrece desactivar. Las FK usan `restrictOnDelete` como segunda defensa, de modo que ni un error de
  código permite borrar historial. Año vigente: no se desactiva ni se elimina. Nivel con grados activos: no se
  desactiva (edge case de la spec).
- **Rationale**: simple, probable y consistente con la FK. Cuando los módulos 3–5 añadan dependientes basta
  con extender `tieneDependientes()` y sus FK. En los Módulos 4 y 5, `AsignacionService::quitar()` deberá rechazar
  asignaciones con asistencia o notas.

## R-10 — Despliegue de permisos: T086 trasladada a este módulo (FR-027, SC-006)

- **Hallazgo**: la especificación 002 registró T086 («sincronizar roles y permisos en cada despliegue») en la
  rama `002-parte-c` (commit `c461b4b`, aún sin fusionar a `main`) con `db:seed --class=RolesYPermisosSeeder`.
  En `main`, `deploy/deploy.sh` solo ejecuta `php artisan migrate --force`. `RolesYPermisosSeeder` ya es seguro
  de repetir: asigna un permiso a sus roles solo la primera vez que lo crea, por lo que no revierte cambios del
  Administrador.
- **Decision** (aprobada): T086 queda **trasladada** a este módulo (se marcó `[~]` en la 002). Como el Módulo 2
  trae varios datos base (permisos, roles y escalas), se crea el comando idempotente `sge:sincronizar-base`:
  ejecuta una lista fija de seeders base idempotentes (`RolesYPermisosSeeder`, `EscalasCalificacionSeeder`),
  **nunca** `AdministradorInicialSeeder` ni `LoadTestSeeder`, con una prueba que lo verifica. Acepta `--force`
  (como `db:seed`) para producción. `deploy.sh` lo llama justo después de `migrate` y antes de recrear
  `app`/`web`, con `dc run --rm --no-deps app …` y el mismo manejo de fallo.
- **Se conserva de T086**: la prueba de idempotencia y la documentación en `docs/despliegue/`; y la nota de
  que **los permisos eliminados o renombrados se limpian a mano** (el comando solo crea).
- **Rationale**: cumple FR-027 sin pasos manuales ni ejecutar `DatabaseSeeder` completo.
- **Alternatives**: `db:seed --class=RolesYPermisosSeeder` como en T086 (no cubre las escalas y esparce la
  lista de seeders en el script); paso manual (prohibido por FR-027).

## R-11 — Normalización de nombres e identificadores (Edge cases)

- **Decision**: mutadores en los modelos recortan y colapsan espacios (`preg_replace('/\s+/u', ' ', trim())`).
  La unicidad la decide el índice único bajo `utf8mb4_unicode_ci` («a» = «A»; también ignora tildes). El
  identificador de sección se guarda en mayúsculas. Los formularios validan con `Rule::unique` sobre el valor
  ya normalizado y capturan la `QueryException` de índice como respaldo.
- **Rationale**: la normalización en el servidor evita depender del navegador; la base es la última defensa.

## R-12 — Cambio de grados de un curso con asignaciones (laguna de la spec)

- **Decision**: al editar un curso, quitar un grado se rechaza si existen asignaciones de ese curso en
  secciones de ese grado; cambiar el nivel del curso se rechaza si tiene grados o asignaciones asociados.
- **Rationale**: sin esta regla se podrían dejar asignaciones apuntando a un grado que ya no dicta el curso,
  violando FR-016 retroactivamente. Es una consecuencia directa de FR-012/FR-016, no alcance nuevo.

## R-13 — Interfaz y pruebas

- **Decision**: componentes Livewire de clase con las convenciones del contrato `componentes-livewire.md` del
  Módulo 1 (`authorize()` al inicio de cada acción, `#[Url]` en búsqueda, `WithPagination` de 15 filas).
  Elementos en el menú vía `config/menu.php` con su permiso. Pruebas Feature por rol con `Livewire::test`
  y peticiones HTTP; la prueba de volumen sigue el patrón de `ListadoUsuariosTest`.
- **Rationale**: coherencia con el código existente y mínima curva de aprendizaje.

## R-14 — Cómo se prueban las reglas de concurrencia

- **Decision**: igual que el Módulo 1 (`ProteccionAdministracionTest`): no se simulan transacciones
  concurrentes reales (las pruebas Feature corren dentro de una transacción de `RefreshDatabase`). Se comprueba
  **por separado** (a) el índice único (insertar el duplicado lanza `QueryException`: dos años vigentes, dos
  asignaciones del mismo curso y sección, dos periodos con el mismo orden) y (b) el bloqueo (con `DB::listen` se
  verifica que el servicio emite `for update` dentro de su transacción y sobre qué tabla).
- **Rationale**: es el enfoque ya aceptado en el proyecto; el índice garantiza el resultado y el bloqueo
  serializa a los administradores.
