# Modelo de datos: Matrícula

**Feature**: `004-matricula` · Migraciones `2026_11_05_0000NN_*` (orden: tipos → alumnos → vínculos → matrículas).

## `tipos_documento` (datos base)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| codigo | string(10) | único (`DNI`, `CE`, `OTRO`) |
| nombre | string(60) | |
| longitud_min / longitud_max | tinyint unsigned | |
| solo_digitos | boolean | DNI: sí |
| activo | boolean | por defecto sí |

## `alumnos`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombres, apellidos | string(100) | obligatorios; índice `(apellidos, nombres)` |
| tipo_documento_id | FK → tipos_documento | restrict |
| numero_documento | string(20) | normalizado; índice; **único con tipo** `(tipo_documento_id, numero_documento)` |
| fecha_nacimiento | date | no futura |
| sexo | string(1) | `F` / `M` |
| usuario_id | FK nullable → usuarios | **único**; null on delete restrict; usuario con `matricula.ser_alumno` y activo |
| activo | boolean | por defecto sí |
| timestamps | | |

Reglas: eliminar solo sin matrículas; desactivar solo sin matrícula activa; DNI = 8 dígitos (de `tipos_documento`).

## `alumno_apoderado`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| alumno_id | FK → alumnos | cascade al eliminar alumno sin matrículas |
| usuario_id | FK → usuarios | restrict; usuario con `matricula.ser_apoderado` y activo |
| parentesco | string(20) | `madre`, `padre`, `tutor`, `otro` |
| timestamps | | |

Único `(alumno_id, usuario_id)`; índice en `usuario_id` (consulta «mis hijos»).

## `matriculas`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| alumno_id | FK → alumnos | restrict |
| seccion_id | FK → secciones | restrict (la sección con matrículas no se elimina) |
| anio_id | FK → anios_escolares | copia del año de la sección, fijada al crear |
| fecha_matricula | date | |
| estado | string(12) | `matriculado`, `retirado`, `trasladado` |
| fecha_estado | date nullable | fecha del retiro/traslado, ≥ `fecha_matricula` |
| motivo | string(255) nullable | |
| anio_activa_id | generada almacenada | `IF(estado='matriculado', anio_id, NULL)` |
| timestamps | | |

Índices: único `(alumno_id, anio_activa_id)` (una activa por alumno y año); `(seccion_id, estado)` (nómina);
`(alumno_id, anio_id)` (historial); FK de `anio_id`.

## Transiciones de estado

```text
matriculado ──retirar──▶ retirado ──corregir*──▶ matriculado
matriculado ──trasladar▶ trasladado ──corregir*─▶ matriculado
(* solo si el alumno no tiene otra matrícula activa en ese año)
retirado/trasladado ──matricular de nuevo──▶ nueva matrícula (la anterior queda en el historial)
```

## Permisos nuevos

Ver [contracts/permisos-y-pertenencia.md](contracts/permisos-y-pertenencia.md).
