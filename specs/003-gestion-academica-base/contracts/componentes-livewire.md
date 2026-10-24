# Contrato: Componentes Livewire y servicios

**Feature**: `003-gestion-academica-base`

Convenciones de `specs/001-auth-roles-rbac/contracts/componentes-livewire.md` (Livewire 4 de clase,
`authorize()` al inicio de cada acción, `WithPagination` de 15, `#[Url]` en búsqueda, `with()` en listados).
Los errores de regla se muestran con `addError('general', …)`. Cada fila requiere Feature test permitido y
denegado (SC-003, FR-026).

## Acciones por componente

| Componente | Acción | Permiso | Resultado / errores |
|---|---|---|---|
| `Institucion\Edit` | `guardar` | `academico.gestionar` | nombre, código modular, UGEL/DRE obligatorios; ≥ 1 nivel; escala existente. |
| `Niveles\Index` | `crear`, `editar`, `desactivar`, `reactivar`, `eliminar` | `academico.gestionar` | nombre y orden únicos; no desactiva con grados activos; elimina solo sin dependientes. |
| `AniosEscolares\Index` | `marcarVigente(id)` | `academico.gestionar` | `AnioEscolarService`; rechaza año inactivo. |
| | `desactivar`, `reactivar`, `eliminar` | `academico.gestionar` | no sobre el vigente; elimina solo sin secciones. |
| `AniosEscolares\Form` | `guardar` | `academico.gestionar` | nombre único; inicio < fin; tipo de periodo; cantidad de periodos 4 o 3; reglas R-02; fechas del año no dejan periodos fuera. |
| `Grados\Index` | `crear`, `editar`, `desactivar`, `reactivar`, `eliminar` | `academico.gestionar` | nombre y orden únicos por nivel; nivel activo. |
| `Secciones\Index` | `crear(grado, año, identificador)`, `crearVarias`, `editar`, `desactivar`, `reactivar`, `eliminar` | `academico.gestionar` | identificador único por grado y año; grado y año activos; elimina solo sin asignaciones. Filtros: año, nivel, grado. |
| `Cursos\Index` | `desactivar`, `reactivar`, `eliminar` | `academico.gestionar` | elimina solo sin asignaciones. |
| `Cursos\Form` | `guardar` | `academico.gestionar` | nivel + ≥ 1 grado del mismo nivel; nombre único por nivel; reglas R-12. |
| `Asignaciones\Index` | (consulta) | `ver_todas` o `ver_propias` | `AsignacionDocente::visiblePara()` con `with(['usuario','curso','seccion.grado.nivel','seccion.anio'])`; filtros año, sección, curso, docente (los de docente solo con `ver_todas`); insignia «reasignar» (R-06). |
| | `quitar(id)` | `asignaciones.gestionar` | solo año vigente. |
| `Asignaciones\Form` | `guardar` | `asignaciones.gestionar` | sección (solo del año vigente), cursos (varios), docente elegible; todo o nada (R-08). `reasignar(id)` cambia el docente. |
| `Asignaciones\Show` | (consulta) | `ver_todas` o `ver_propias` | `autorizarPertenencia()` antes de cargar datos. |

## Servicios (`app/Academico`)

```php
AnioEscolarService::marcarVigente(AnioEscolar $anio): void        // R-01; error si inactivo
AnioEscolarService::guardarPeriodos(AnioEscolar $anio, array $periodos): void  // R-02, transaccional
AsignacionService::docentesElegibles(?string $busqueda): Builder   // activo + permiso marcador (R-05)
AsignacionService::asignar(Seccion $seccion, Curso $curso, Usuario $docente): AsignacionDocente
AsignacionService::asignarVarios(Seccion $seccion, array $cursoIds, Usuario $docente): Collection  // todo o nada
AsignacionService::reasignar(AsignacionDocente $a, Usuario $docente): void
AsignacionService::quitar(AsignacionDocente $a): void             // solo año vigente
```

Los servicios lanzan `ReglaAcademicaIncumplida` (excepción de dominio con mensaje en español y, en
`asignarVarios`, el curso que falló); los componentes la convierten en error de formulario.
