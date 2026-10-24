# Contrato: Rutas web

**Feature**: `003-gestion-academica-base`

HTML (Blade/Livewire) bajo el grupo existente `auth` + `activo`. Sin permiso → **403**. Todas con prefijo
`/academico`.

| Método | URI | Nombre | Componente | Permiso (middleware / `mount`) |
|---|---|---|---|---|
| GET | `/academico/institucion` | `academico.institucion` | `Institucion\Edit` | `academico.ver` (ver) · `academico.gestionar` (guardar) |
| GET | `/academico/niveles` | `academico.niveles.index` | `Niveles\Index` | `academico.ver` |
| GET | `/academico/anios` | `academico.anios.index` | `AniosEscolares\Index` | `academico.ver` |
| GET | `/academico/anios/crear` | `academico.anios.create` | `AniosEscolares\Form` | `academico.gestionar` |
| GET | `/academico/anios/{anio}/editar` | `academico.anios.edit` | `AniosEscolares\Form` | `academico.gestionar` |
| GET | `/academico/grados` | `academico.grados.index` | `Grados\Index` | `academico.ver` |
| GET | `/academico/secciones` | `academico.secciones.index` | `Secciones\Index` | `academico.ver` |
| GET | `/academico/cursos` | `academico.cursos.index` | `Cursos\Index` | `academico.ver` |
| GET | `/academico/cursos/crear` · `/{curso}/editar` | `academico.cursos.create` / `.edit` | `Cursos\Form` | `academico.gestionar` |
| GET | `/academico/asignaciones` | `academico.asignaciones.index` | `Asignaciones\Index` | `asignaciones.ver_todas` |
| GET | `/academico/mis-asignaciones` | `academico.asignaciones.propias` | `Asignaciones\Index` | `asignaciones.ver_propias` |
| GET | `/academico/asignaciones/crear` | `academico.asignaciones.create` | `Asignaciones\Form` | `asignaciones.gestionar` |
| GET | `/academico/asignaciones/{asignacion}` | `academico.asignaciones.show` | `Asignaciones\Show` | `asignaciones.ver_todas` **o** `asignaciones.ver_propias` + `autorizarPertenencia()` |

- Las altas y ediciones simples de grados y secciones se hacen en línea desde su listado (formulario en la
  misma pantalla), con `academico.gestionar` verificado en cada acción.
- `{asignacion}` ajena para un Docente → 403 sin revelar datos (la comprobación de pertenencia ocurre antes
  de cargar relaciones).
- Menú (`config/menu.php`): Institución, Años escolares, Grados, Secciones, Cursos (`academico.ver`);
  Asignaciones (`asignaciones.ver_todas`); Mis asignaciones (`asignaciones.ver_propias`).
- Cada ruta con permiso doble o condicional (`Show`) lo comprueba en `mount()`, porque `can:` solo admite una
  habilidad.
