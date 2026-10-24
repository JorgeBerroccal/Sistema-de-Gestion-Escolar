# Contrato: Permisos y pertenencia

**Feature**: `003-gestion-academica-base`

## Permisos nuevos (se añaden a `RolesYPermisosSeeder::PERMISOS`)

Formato validado por `Permiso::REGEX_IDENTIFICADOR`. Se asignan a los roles **solo al crearse** el permiso
(comportamiento actual del seeder), por lo que no pisan cambios posteriores del Administrador.

| Identificador | Nombre visible | Administrador | Director | Docente | Padre de Familia | Alumno |
|---|---|:-:|:-:|:-:|:-:|:-:|
| `academico.ver` | Ver institución, años, periodos, niveles, grados, secciones y cursos | ✔ | ✔ | | | |
| `academico.gestionar` | Gestionar institución, años, periodos, niveles, grados, secciones y cursos | ✔ | | | | |
| `asignaciones.ver_todas` | Ver todas las asignaciones docentes | ✔ | ✔ | | | |
| `asignaciones.ver_propias` | Ver las asignaciones propias | | | ✔ | | |
| `asignaciones.gestionar` | Crear, reasignar y quitar asignaciones | ✔ | | | | |
| `docencia.dictar` | Puede recibir asignaciones como docente (marcador de elegibilidad) | | | ✔ | | |

`academico.gestionar` no implica `academico.ver`: cada pantalla exige su permiso y los roles por defecto
reciben ambos donde corresponde. `docencia.dictar` **no concede acceso**: solo marca qué roles pueden
figurar como docente (R-05); no se usa en middleware ni policies de acceso.

## Regla de pertenencia: `AsignacionDocenteRegla`

Registrada en `config/pertenencia.php`: `AsignacionDocente::class => AsignacionDocenteRegla::class`.

| Situación del usuario | `aplicar()` | `permite()` |
|---|---|---|
| Tiene `asignaciones.ver_todas` | sin filtro | `true` |
| Tiene `asignaciones.ver_propias` | `where usuario_id = {id}` | `registro.usuario_id === usuario.id` |
| Ninguno de los dos | `whereRaw('1 = 0')` | `false` |
| Regla no registrada (modelo con el trait) | `ReglaPertenenciaFaltante` → 403 | 403 |

Nunca se compara el nombre del rol. Quien tiene ambos permisos ve todo (la prioridad es de `ver_todas`).

## Orden de verificación

1. RBAC (`can:` / `authorize()`) → 403 sin permiso.
2. Pertenencia (`visiblePara()` / `autorizarPertenencia()`) → filtra o 403.

## Pruebas obligatorias (FR-026)

Por permiso: permitido y denegado para cada rol por defecto, y para un rol sin permiso creado en la prueba.
Pertenencia: Docente A no ve ni abre la asignación de Docente B (403); sin regla registrada → 403; Director y
Administrador ven todo; Director no ejecuta ninguna acción de modificación (403).
