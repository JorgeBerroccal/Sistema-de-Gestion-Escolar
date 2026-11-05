# Contrato: Permisos y pertenencia

**Feature**: `005-asistencia`

## Permisos nuevos (se añaden a `RolesYPermisosSeeder::PERMISOS`)

Se asignan a los roles **solo al crearse** el permiso. Total en staging tras el módulo: 20 + 4 = **24**.

| Identificador | Nombre visible | Administrador | Director | Docente | Padre de Familia | Alumno |
|---|---|:-:|:-:|:-:|:-:|:-:|
| `asistencia.ver_todas` | Ver la asistencia y los reportes de todas las secciones | ✔ | ✔ | | | |
| `asistencia.gestionar` | Tomar, corregir y justificar asistencia en cualquier sección | ✔ | | | | |
| `asistencia.registrar_propia` | Tomar, corregir, justificar y reportar en las secciones propias | | | ✔ | | |
| `asistencia.ver_propia` | Ver la asistencia propia o de los hijos | | | | ✔ | ✔ |

`gestionar` no implica `ver_todas`. Sin permiso marcador nuevo: se reutilizan las asignaciones (Módulo 2) y los vínculos (Módulo 3).

## Regla de pertenencia (en `config/pertenencia.php`)

`Asistencia::class => AsistenciaRegla::class`

| Situación del usuario | `AsistenciaRegla::aplicar` |
|---|---|
| `asistencia.ver_todas` | sin filtro |
| `asistencia.registrar_propia` | registros de secciones con asignación mía |
| `asistencia.ver_propia` | registros de alumnos con `usuario_id` = yo o vínculo de apoderado conmigo |
| combinación | unión de lo anterior |
| ninguno / regla no registrada | `whereRaw('1 = 0')` / `ReglaPertenenciaFaltante` → 403 |

**Escritura**: tomar, corregir o justificar exige `asistencia.gestionar` (cualquier sección) o
`asistencia.registrar_propia` + asignación en la sección; en ambos casos, año vigente. Nunca se compara el nombre del rol.

## Orden de verificación

1. RBAC (`can:` o `mount()`) → 403 sin permiso.
2. Pertenencia (sección/alumno visible) antes de cargar relaciones → filtra o 403.
3. Reglas de fecha y año vigente → error de validación (sin datos personales en el mensaje).

## Pruebas obligatorias (FR-013)

Permitido y denegado por cada rol por defecto y por un rol sin permiso creado en la prueba. Docente sin asignación
no abre ni guarda otra sección; Padre/Alumno no ven asistencia ajena ni pueden editar; Director consulta pero no
modifica; quitar `asistencia.ver_todas` al Director corta su acceso; usuario sin vínculos → lista vacía.
