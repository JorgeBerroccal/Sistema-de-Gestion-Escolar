# Contrato: Permisos y pertenencia

**Feature**: `006-evaluacion-boletas`

## Permisos nuevos (se añaden a `RolesYPermisosSeeder::PERMISOS`)

Se asignan a los roles **solo al crearse** el permiso. Total en staging tras el módulo: 24 + 8 = **32**.

| Identificador | Nombre visible | Administrador | Director | Docente | Padre de Familia | Alumno |
|---|---|:-:|:-:|:-:|:-:|:-:|
| `notas.ver_todas` | Ver notas y reportes de todas las secciones | ✔ | ✔ | | | |
| `notas.gestionar` | Definir criterios y registrar o corregir notas en cualquier sección, incluso con periodo cerrado | ✔ | | | | |
| `notas.registrar_propia` | Definir criterios, registrar y reportar notas de los cursos y secciones propios | | | ✔ | | |
| `notas.ver_propia` | Ver las notas propias o de los hijos (periodos publicados) | | | | ✔ | ✔ |
| `notas.cerrar_periodo` | Cerrar y reabrir periodos por sección | ✔ | | | | |
| `boletas.gestionar` | Generar la boleta de cualquier alumno, también borrador | ✔ | | | | |
| `boletas.generar_propia` | Generar la boleta de los alumnos de las secciones propias, también borrador | | | ✔ | | |
| `boletas.ver_propia` | Descargar la boleta propia o de los hijos (periodos publicados) | | | | ✔ | ✔ |

`gestionar` no implica `ver_todas` (igual que Matrícula y Asistencia). Sin permiso marcador nuevo: se reutilizan
`docencia.dictar`, las asignaciones (Módulo 2) y los vínculos (Módulo 3).

## Reglas de pertenencia (en `config/pertenencia.php`)

`CriterioEvaluacion::class => CriterioRegla::class` y `Nota::class => NotaRegla::class`

| Situación del usuario | `aplicar` |
|---|---|
| `notas.ver_todas` | sin filtro |
| `notas.registrar_propia` | registros cuyo par (sección, curso) tiene asignación mía |
| `notas.ver_propia` | registros de alumnos con `usuario_id` = yo o vínculo de apoderado conmigo **y** (sección, periodo) con fila en `cierres_periodo` |
| combinación | unión (OR) de lo anterior |
| ninguno / regla no registrada | `whereRaw('1 = 0')` / `ReglaPertenenciaFaltante` → 403 |

**Escritura** (esquema, notas): `notas.gestionar` (cualquier sección) o `notas.registrar_propia` + asignación en el par;
ambos exigen año vigente; el Docente exige además periodo abierto. **Cierre/reapertura**: `notas.cerrar_periodo`.
**Boleta**: ver `contracts/rutas-web.md`. Nunca se compara el nombre del rol.

## Orden de verificación

1. RBAC (`can:` o `mount()`) → 403 sin permiso.
2. Pertenencia (sección/par/alumno visible) antes de cargar relaciones → filtra o 403.
3. Año vigente, periodo abierto, escala y suma de pesos → error de validación (sin datos personales en el mensaje).

## Pruebas obligatorias (FR-014, SC-003)

Permitido y denegado por cada rol por defecto y por un rol sin permiso creado en la prueba. Docente sin asignación
no abre ni guarda otro par; Docente con periodo cerrado no guarda y el Administrador sí; Padre/Alumno no ven notas
ajenas ni de periodos sin cerrar ni descargan boletas ajenas o en borrador; Director consulta pero no modifica;
quitar `notas.ver_todas` al Director corta su acceso; usuario sin vínculos → lista vacía; solo el Administrador
cierra y reabre.
