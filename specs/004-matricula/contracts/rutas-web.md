# Contrato: Rutas web

**Feature**: `004-matricula`

HTML (Blade/Livewire) bajo el grupo `auth` + `activo`, prefijo `/matricula`. Sin permiso → **403**.

| Método | URI | Nombre | Componente | Permiso |
|---|---|---|---|---|
| GET | `/matricula/alumnos` | `matricula.alumnos.index` | `Alumnos\Index` | `matricula.ver_todas` |
| GET | `/matricula/alumnos/crear` · `/{alumno}/editar` | `matricula.alumnos.create` / `.edit` | `Alumnos\Form` | `matricula.gestionar` |
| GET | `/matricula/alumnos/{alumno}` | `matricula.alumnos.show` | `Alumnos\Show` | `ver_todas` **o** `ver_propia` + `autorizarPertenencia()` |
| GET | `/matricula/nominas` | `matricula.nominas.index` | `Nominas\Index` | `ver_todas` **o** `ver_nomina_propia` |
| GET | `/matricula/nominas/{seccion}` | `matricula.nominas.show` | `Nominas\Show` | `ver_todas` **o** `ver_nomina_propia` + sección visible |
| GET | `/matricula/mis-alumnos` | `matricula.propios.index` | `MisAlumnos\Index` | `matricula.ver_propia` |

- `Alumnos\Show` ofrece (con `matricula.gestionar`): vincular/quitar usuario, agregar/quitar apoderados,
  matricular, retirar/trasladar/corregir y desactivar/reactivar/eliminar.
- `Nominas\Show` ofrece (con `matricula.gestionar`) la matrícula masiva de alumnos elegibles; muestra
  nombres, apellidos, documento, estado y fecha. Enlaza a la ficha solo si el usuario puede verla.
- `Nominas\Index` lista las secciones visibles (año vigente por defecto, filtro de año); para el Docente,
  solo las secciones con una asignación suya.
- Un `{alumno}` o `{seccion}` ajeno → 403 sin revelar datos; la pertenencia se comprueba antes de cargar relaciones.
- Menú: Alumnos y Nóminas (`matricula.ver_todas`), Mis secciones (`ver_nomina_propia`, ruta de nóminas),
  Mis alumnos (`matricula.ver_propia`).
- Cada ruta con permiso doble lo comprueba en `mount()`, porque `can:` solo admite una habilidad.
