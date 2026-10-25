# Data Model: Gestión Académica Base

**Feature**: `003-gestion-academica-base` | **Date**: 2026-10-24

Motor MySQL 8, `utf8mb4` / `utf8mb4_unicode_ci`. Todas las tablas tienen `created_at` y `updated_at`.
Toda FK usa `restrictOnDelete` (salvo las pivote indicadas) y lleva índice. Los nombres de tablas y modelos
están en español, como en el Módulo 1 (los modelos declaran `$table`).

```text
instituciones 1 ──< institucion_nivel >── niveles 1 ──< grados 1 ──< secciones >── 1 anios_escolares 1 ──< periodos_evaluacion
instituciones >── 1 escalas_calificacion              niveles 1 ──< cursos >──< curso_grado >──< grados
asignaciones_docentes >── secciones, cursos, usuarios (Módulo 1)
```

## instituciones (`Institucion`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | tinyint unsigned PK | `CHECK (id = 1)`: una sola ficha (R-04) |
| nombre | varchar(150) | obligatorio |
| codigo_modular | varchar(20) | obligatorio |
| ugel_dre | varchar(150) | obligatorio (UGEL o dirección regional) |
| escala_calificacion_id | FK → escalas_calificacion | nullable hasta que se elija |

- `Institucion::actual()` devuelve la ficha (o una nueva sin guardar). Guardar exige al menos un nivel
  atendido (validación del formulario; ver `institucion_nivel`).

## niveles (`Nivel`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(60) | obligatorio, único |
| orden | smallint unsigned | obligatorio, único |
| activo | boolean | por defecto `true`, índice |

- Desactivar: rechazado si tiene grados activos. Eliminar: solo sin grados, cursos ni institución vinculada.

## institucion_nivel (pivote)

| Campo | Tipo | Reglas |
|---|---|---|
| institucion_id | tinyint unsigned, FK → instituciones | `ON DELETE CASCADE` (mismo tipo que `instituciones.id`) |
| nivel_id | FK → niveles | `ON DELETE RESTRICT` |

PK compuesta. Marca los niveles que atiende la institución (mínimo uno, FR-002). Quitar un nivel atendido
que ya tiene grados activos se rechaza.

## escalas_calificacion (`EscalaCalificacion`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| codigo | varchar(20) | único (`literal`, `vigesimal`) |
| nombre | varchar(60) | obligatorio |
| valores | json | literal: lista ordenada de calificativos; vigesimal: `{minimo, maximo}` |

Dos filas fijas cargadas por `EscalasCalificacionSeeder` (carga base, idempotente por `codigo`). No hay CRUD.

## anios_escolares (`AnioEscolar`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(30) | obligatorio, único (normalizado) |
| fecha_inicio / fecha_fin | date | obligatorios, inicio < fin |
| tipo_periodo | varchar(15) | `bimestre` o `trimestre` (enum `TipoPeriodo`) |
| vigente | tinyint nullable | **único**: `1` o `NULL` (R-01) |
| activo | boolean | por defecto `true`, índice |

- Índices: único en `nombre`, único en `vigente`, índice en `activo`, índice en `fecha_inicio` (orden del listado).
- Reglas: no se desactiva ni elimina el vigente; no se marca vigente un año inactivo; cambiar fechas del año se
  rechaza si algún periodo quedaría fuera; cambiar `tipo_periodo` solo si no hay dependientes de los periodos
  (hoy ninguno) y regenera los periodos vacíos.
- Eliminar: solo sin secciones. `tieneDependientes()` = existen secciones (y, a futuro, datos de otros módulos).

## periodos_evaluacion (`PeriodoEvaluacion`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| anio_escolar_id | FK → anios_escolares | `ON DELETE CASCADE` (los periodos no tienen vida propia) |
| orden | tinyint unsigned | 1..4 o 1..3 según el tipo; único con el año |
| nombre | varchar(40) | propuesto («I Bimestre»…), editable |
| fecha_inicio / fecha_fin | date | dentro del año; inicio < fin; `fin` anterior al `inicio` del siguiente (R-02) |

Único `(anio_escolar_id, orden)`. La validación del conjunto vive en `AnioEscolarService`.

## grados (`Grado`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nivel_id | FK → niveles | |
| nombre | varchar(40) | obligatorio, normalizado |
| orden | smallint unsigned | obligatorio |
| activo | boolean | por defecto `true`, índice |

Únicos `(nivel_id, nombre)` y `(nivel_id, orden)`. `tieneDependientes()` = secciones o cursos asociados.

## secciones (`Seccion`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| grado_id | FK → grados | |
| anio_escolar_id | FK → anios_escolares | |
| identificador | varchar(10) | mayúsculas, normalizado |
| activo | boolean | por defecto `true`, índice |

Único `(grado_id, anio_escolar_id, identificador)`; índice en `anio_escolar_id`. Cantidad por grado libre.
`tieneDependientes()` = asignaciones. Una sección de un año no vigente no admite asignaciones nuevas.

## cursos (`Curso`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nivel_id | FK → niveles | |
| nombre | varchar(100) | obligatorio, normalizado; único con el nivel |
| activo | boolean | por defecto `true`, índice |

## curso_grado (pivote)

| Campo | Tipo | Reglas |
|---|---|---|
| curso_id | FK → cursos | `ON DELETE CASCADE` |
| grado_id | FK → grados | `ON DELETE RESTRICT` |

PK compuesta, índice en `grado_id`. Regla: `grados.nivel_id = cursos.nivel_id` (validada en el formulario y en
el servicio; no expresable como restricción). Quitar un grado con asignaciones de ese curso en él: rechazado
(R-12). Al menos un grado.

## asignaciones_docentes (`AsignacionDocente`, usa `RequierePertenencia`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| seccion_id | FK → secciones | implica grado y año |
| curso_id | FK → cursos | |
| usuario_id | FK → usuarios (Módulo 1) | docente; índice para «Mis asignaciones» |

- Único `(seccion_id, curso_id)`: un docente por curso y sección (FR-017).
- Reglas de creación/reasignación (en `AsignacionService`, dentro de transacción, R-08): sección del **año
  vigente**, año/sección/curso activos, `curso_grado` contiene el grado de la sección, docente elegible
  (activo y con `docencia.dictar`, R-05).
- Reasignar cambia solo `usuario_id`. Quitar elimina la fila (solo del año vigente).
- Derivado: `pendienteReasignar` = docente inactivo o sin `docencia.dictar` (R-06).
- Índices adicionales: `(usuario_id, seccion_id)` para el filtro propio; `curso_id`.

### Transiciones de estado (activo / inactivo)

```text
ACTIVO ── desactivar ──▶ INACTIVO ── reactivar ──▶ ACTIVO      (año, grado, sección, curso, nivel)
```

- Desactivar año: prohibido si es vigente. Desactivar nivel: prohibido con grados activos.
- Reactivar año: permitido. Marcar vigente: solo un año activo.
- Un elemento inactivo no se ofrece en selectores de nuevas asignaciones, pero sus asignaciones siguen visibles.

## Datos iniciales

| Seeder | Crea | Cuándo |
|---|---|---|
| `RolesYPermisosSeeder` (ampliado) | 6 permisos nuevos y su asignación por defecto (ver contrato) | `DatabaseSeeder` y `sge:sincronizar-base` |
| `EscalasCalificacionSeeder` | 2 escalas | `DatabaseSeeder` y `sge:sincronizar-base` |
| `AcademicoDemoSeeder` | institución ficticia, niveles, grados, secciones, cursos, docentes y asignaciones de ejemplo | `APP_ENV=local`; en staging por ejecución explícita; **rechaza producción** |

SC-007: un segundo conjunto de datos de ejemplo con otros niveles y cantidades se carga en las pruebas con
fábricas, sin cambiar código.
