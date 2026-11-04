# Contrato: Permisos y pertenencia

**Feature**: `004-matricula`

## Permisos nuevos (se añaden a `RolesYPermisosSeeder::PERMISOS`)

Se asignan a los roles **solo al crearse** el permiso (comportamiento actual del seeder).

| Identificador | Nombre visible | Administrador | Director | Docente | Padre de Familia | Alumno |
|---|---|:-:|:-:|:-:|:-:|:-:|
| `matricula.ver_todas` | Ver todos los alumnos, nóminas y matrículas | ✔ | ✔ | | | |
| `matricula.gestionar` | Gestionar alumnos, vínculos y matrículas | ✔ | | | | |
| `matricula.ver_nomina_propia` | Ver las nóminas de las secciones propias | | | ✔ | | |
| `matricula.ver_propia` | Ver los alumnos y matrículas propios o de los hijos | | | | ✔ | ✔ |
| `matricula.ser_alumno` | Puede vincularse a una ficha de alumno (marcador) | | | | | ✔ |
| `matricula.ser_apoderado` | Puede vincularse como apoderado (marcador) | | | | ✔ | |

`matricula.gestionar` no implica `matricula.ver_todas`. Los marcadores `ser_*` no conceden acceso.

## Reglas de pertenencia (registradas en `config/pertenencia.php`)

`Alumno::class => AlumnoRegla::class` · `Matricula::class => MatriculaRegla::class`

| Situación del usuario | `AlumnoRegla::aplicar` | `MatriculaRegla::aplicar` |
|---|---|---|
| `matricula.ver_todas` | sin filtro | sin filtro |
| `matricula.ver_propia` | alumnos con `usuario_id` = yo **o** con vínculo de apoderado conmigo | matrículas de esos alumnos |
| `matricula.ver_nomina_propia` | ninguno | matrículas de secciones con asignación mía |
| combinación | unión de lo anterior | unión de lo anterior |
| ninguno | `whereRaw('1 = 0')` | `whereRaw('1 = 0')` |
| regla no registrada | `ReglaPertenenciaFaltante` → 403 | ídem |

`permite()` aplica la misma lógica sobre un registro. Nunca se compara el nombre del rol.

## Orden de verificación

1. RBAC (`can:` o `mount()`) → 403 sin permiso.
2. Pertenencia (`visiblePara()` / `autorizarPertenencia()`), antes de cargar relaciones → filtra o 403.

## Pruebas obligatorias (FR-029)

Permitido y denegado por cada rol por defecto y por un rol sin permiso creado en la prueba. Docente A no abre la
nómina de una sección sin asignación suya ni la ficha de un alumno (403); un Padre no abre a un alumno ajeno;
un Alumno no ve a sus compañeros; usuario sin vínculos → lista vacía; Director no ejecuta modificaciones;
quitar `matricula.ver_todas` al Director corta su acceso.
