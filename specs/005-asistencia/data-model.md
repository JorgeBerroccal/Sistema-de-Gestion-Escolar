# Modelo de datos: Asistencia

**Feature**: `005-asistencia` · Migraciones `2026_11_06_0000NN_*` (orden: estados → asistencias).

## `estados_asistencia` (datos base)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| codigo | string(20) | único (`presente`, `tarde`, `falta`, `falta_justificada`) |
| nombre | string(40) | etiqueta visible |
| cuenta_asistencia | boolean | presente y tarde: sí |
| cuenta_falta | boolean | falta: sí; falta justificada: no (se muestra aparte) |
| justificado_id | FK nullable → estados_asistencia | falta → falta justificada; restrict; índice |
| orden | tinyint unsigned | orden en pantalla |
| activo | boolean | por defecto sí |

## `asistencias`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| seccion_id | FK → secciones | restrict |
| alumno_id | FK → alumnos | restrict |
| fecha | date | no futura, ni sábado ni domingo, dentro del año de la sección |
| estado_id | FK → estados_asistencia | restrict; índice |
| observacion | string(255) nullable | |
| motivo_justificacion | string(255) nullable | solo con estado justificado |
| registrado_por | FK → usuarios | quien guardó/corrigió por última vez; restrict |
| timestamps | | |

Índices: **único** `(seccion_id, alumno_id, fecha)`; `(alumno_id, fecha)`; `(seccion_id, fecha)`.

## Reglas y transiciones

- Un alumno entra en la lista de una fecha según R-03 (matrícula) del [research](research.md).
- Estado: cualquiera del catálogo activo; `falta` ⇄ `falta_justificada` por la acción de justificar/revertir
  (con motivo al justificar; el motivo se limpia al revertir).
- Registros de años no vigentes: solo lectura. Alumno desactivado: conserva su historial.
- Resumen: `% asistencia` = `cuenta_asistencia` / (`cuenta_asistencia` + `cuenta_falta`) sobre los registros del
  rango; las faltas justificadas no entran en el denominador (denominador 0 → sin datos). Ranking: solo `cuenta_falta`.

## Relaciones

`Asistencia` → `Seccion`, `Alumno` (Módulo 3), `EstadoAsistencia`, `Usuario` (registrador).
La matrícula determina la nómina; la asignación docente determina quién edita.
