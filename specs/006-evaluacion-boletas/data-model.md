# Modelo de datos: Evaluación y Boletas

**Feature**: `006-evaluacion-boletas` · Motor MySQL 8, `utf8mb4_unicode_ci`. Migraciones `2026_11_13_0000NN_*`.

## Tablas nuevas

### `criterios_evaluacion`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `seccion_id` | FK `secciones` | restrict |
| `curso_id` | FK `cursos` | restrict |
| `periodo_id` | FK `periodos_evaluacion` | restrict |
| `nombre` | string(60) | dato libre del Docente |
| `peso` | decimal(5,2) | > 0 y ≤ 100 |
| `orden` | unsignedTinyInteger | |
| timestamps | | |

Índices: único `(seccion_id, curso_id, periodo_id, nombre)`; `(seccion_id, curso_id, periodo_id, orden)`; `periodo_id`; `curso_id`.
Regla de dominio (no de base): los pesos de un (sección, curso, periodo) suman 100,00 (FR-001).

### `notas`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `criterio_id` | FK `criterios_evaluacion` | **cascade** (eliminar un criterio borra sus notas, R-11) |
| `alumno_id` | FK `alumnos` | restrict |
| `valor` | string(8) | tal cual se escribió («AD», «15.5») |
| `valor_numerico` | decimal(5,2) | derivado con la escala (R-02/R-03) |
| `registrado_por` | FK `usuarios` | restrict |
| timestamps | | |

Índices: **único** `(criterio_id, alumno_id)` (R-02); `alumno_id`.
Celda vacía = sin fila (nunca cero).

### `cierres_periodo`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `seccion_id` | FK `secciones` | restrict |
| `periodo_id` | FK `periodos_evaluacion` | restrict |
| `cerrado_por` | FK `usuarios` | restrict |
| `cerrado_en` | timestamp | |
| timestamps | | |

Índices: **único** `(seccion_id, periodo_id)`; `periodo_id`. Fila presente = periodo cerrado y publicado; reabrir elimina la fila (R-05).

## Tabla modificada: `escalas_calificacion`

| Columna nueva | Tipo | Valor base |
|---|---|---|
| `equivalentes` | json nullable | literal: `{"AD":4,"A":3,"B":2,"C":1}`; vigesimal: `null` |
| `umbral_aprobacion` | string(8) nullable | literal: `B`; vigesimal: `11` |

Los equivalentes y el umbral son **configuración de la institución, no una regla del Currículo Nacional**. La migración rellena las dos escalas por `codigo`; `EscalasCalificacionSeeder` solo completa si están en `null` (R-20).
`valores` y `institucion.escala_calificacion_id` no cambian.

## Relaciones (Eloquent)

- `CriterioEvaluacion` belongsTo `Seccion`, `Curso`, `PeriodoEvaluacion`; hasMany `Nota`.
- `Nota` belongsTo `CriterioEvaluacion`, `Alumno`, `Usuario` (`registrado_por`).
- `CierrePeriodo` belongsTo `Seccion`, `PeriodoEvaluacion`, `Usuario` (`cerrado_por`).
- `CriterioEvaluacion` y `Nota` usan `RequierePertenencia` (reglas en `config/pertenencia.php`).

## Estados y transiciones

`CierrePeriodo` (por sección y periodo): **abierto** (sin fila) → *cerrar* (Administrador, `notas.cerrar_periodo`) → **cerrado/publicado** → *reabrir* → **abierto**.
Mientras está cerrado: el Docente no escribe (R-08); el Administrador sí; Padre y Alumno ven notas y boleta definitiva.

## Reglas de validación

- Esquema: ≥ 1 criterio, pesos > 0 que suman exactamente 100,00 (comparación en centésimas), nombres únicos en el esquema, sección y curso con asignación.
- Nota: `valor` pertenece a la escala de la institución (literal: uno de `valores`; vigesimal: `minimo` ≤ n ≤ `maximo`, hasta 2 decimales; coma o punto).
- Guardado: alumno en la nómina activa de la sección; criterio del esquema vigente del par; todo o nada (R-10).
- Escritura: año de la sección vigente; periodo dentro del año; docente con asignación activa (R-08).

## Datos derivados (no se guardan)

- Promedio de periodo por curso y anual (R-04); aprobado/desaprobado contra `umbral_aprobacion`.
- Orden de mérito y desaprobados por curso (R-15).
- Resumen de asistencia de la boleta (R-14, Módulo 4).
