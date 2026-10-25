# Implementation Plan: Gestión Académica Base

**Branch**: `003-gestion-academica-base` | **Date**: 2026-10-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-gestion-academica-base/spec.md`

**Módulo MVP**: 2 — Gestión Académica Base (Constitución, Principio IV)

## Summary

Configuración académica genérica de una sola institución: ficha, niveles atendidos, escala de calificación,
año escolar vigente con periodos, grados, secciones, cursos y asignación de docentes. Se implementa con las
convenciones nativas ya usadas en el Módulo 1: modelos Eloquent, migraciones, componentes Livewire 4 basados
en clase, autorización por permisos (`Gate::before` contra `rol_permiso`) y el mecanismo de pertenencia
(`RequierePertenencia`) para «Mis asignaciones». Las reglas con riesgo de concurrencia (un solo año vigente,
periodos sin superposición, un docente por curso y sección) se protegen con transacciones, `lockForUpdate` e
índices únicos. La elegibilidad de docente se resuelve con un permiso marcador (`docencia.dictar`), sin
nombres de rol en el código. Los permisos llegan a staging con un comando idempotente
(`sge:sincronizar-base`) que `deploy.sh` ejecuta tras migrar (ver research R-10: sustituye a la tarea T086 de
la especificación 002, trasladada a este módulo).

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13 (`laravel/framework ^13.17`)

**Primary Dependencies**: Livewire 4 (`^4.2`, componentes de clase), Tailwind 4 / Vite (ya presentes). Sin paquetes nuevos.

**Storage**: MySQL 8 (`utf8mb4_unicode_ci`: comparaciones sin distinguir mayúsculas ni tildes). Redis solo sesión, caché y limitador (sin cambios).

**Testing**: Pest 4 (Feature con `RefreshDatabase` sobre MySQL `sge_testing`; Unit sin base de datos). Pruebas de concurrencia de año vigente con transacciones; prueba de volumen para SC-005.

**Target Platform**: Docker Compose, nginx + PHP-FPM; staging en Azure (despliegue de la especificación 002).

**Project Type**: Aplicación web monolítica Laravel + Blade + Livewire (sin API separada).

**Performance Goals**: SC-005 — con 200 secciones y 2000 asignaciones, primera página de cada listado < 2 s y «Mis asignaciones» < 1,5 s.

**Constraints**: Sin nombres de rol ni de institución, nivel, grado, sección, curso o escala en el código (Principios II y III); listados paginados con `with()`; índices en toda FK y columna de filtro/orden; sin colas.

**Scale/Scope**: Una institución; del orden de 200 secciones, 2000 asignaciones y decenas de docentes; ~12 pantallas Livewire.

## Constitution Check

*GATE: pasa antes de la investigación; se reevalúa tras el diseño (resultado abajo).*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, MySQL 8, sin servicios nuevos ni otro lenguaje. | ✅ |
| II. Genérico y configurable | Institución, niveles, grados, secciones, cursos y escalas son tablas. Los únicos valores en código son estructurales (tipo de periodo bimestre/trimestre y sus cantidades 4/3, exigidos por la spec). Datos de ejemplo solo en seeder de ejemplo. | ✅ |
| III. RBAC por tablas | Todo se autoriza con permisos nuevos de `rol_permiso`; elegibilidad de docente por permiso marcador `docencia.dictar`; pertenencia con `RequierePertenencia` + regla registrada en `config/pertenencia.php`, evaluada por permisos (`asignaciones.ver_todas`), no por rol. `SinCondicionalesDeRolTest` sigue en verde. | ✅ |
| IV. Alcance MVP | Módulo 2. Se excluyen horarios, importación, logo, cierre de años y todo lo de Módulos 3–6. Se añade un CRUD mínimo de **niveles** porque sin él un entorno de producción no tendría catálogo (R-03); está dentro de FR-002/FR-009. | ✅ |
| V. Pruebas | Cada regla de autorización y validación tiene Feature test permitido/denegado o válido/inválido (FR-026), más prueba de concurrencia y de volumen. k6 no aplica: ningún endpoint de este módulo es crítico (login, notas, boletas). | ✅ |
| VI. IA restringida | No hay chatbot en este módulo; `AsignacionDocente::visiblePara()` queda listo para el Módulo 6. | ✅ N/A |
| VII. Simplicidad | Eloquent + Livewire + dos clases de servicio con transacciones (precedente: `ProteccionAdministracion`). Sin repositorios, colas ni caché. | ✅ |
| Rendimiento | Índices en FK y filtros, `with()`, `paginate(15)` en todo listado. | ✅ |

**Re-evaluación tras Fase 1**: sin cambios; no hay violaciones, la tabla de complejidad queda vacía.

## Project Structure

### Documentation (this feature)

```text
specs/003-gestion-academica-base/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rutas-web.md
│   ├── componentes-livewire.md
│   └── permisos-y-pertenencia.md
├── checklists/requirements.md
└── tasks.md              # /speckit-tasks (no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Academico/
│   ├── AnioEscolarService.php        # marcar vigente, validar/guardar periodos, cambio de tipo
│   ├── AsignacionService.php         # asignar, asignar varios, reasignar, quitar, docentes elegibles
│   └── TipoPeriodo.php               # enum bimestre|trimestre (cantidad de periodos)
├── Autorizacion/Pertenencia/
│   └── Reglas/AsignacionDocenteRegla.php
├── Livewire/Academico/
│   ├── Institucion/Edit.php
│   ├── Niveles/Index.php
│   ├── AniosEscolares/{Index,Form}.php      # Form incluye periodos
│   ├── Grados/Index.php                      # alta y edición en línea
│   ├── Secciones/Index.php                   # alta y edición en línea
│   ├── Cursos/{Index,Form}.php
│   └── Asignaciones/{Index,Form,Show}.php
├── Models/
│   ├── Institucion.php  Nivel.php  EscalaCalificacion.php  AnioEscolar.php  PeriodoEvaluacion.php
│   └── Grado.php  Seccion.php  Curso.php  AsignacionDocente.php
└── Console/Commands/SincronizarBase.php     # sge:sincronizar-base

config/{menu.php, pertenencia.php}           # ítems de menú y regla registrada
database/
├── migrations/2026_10_24_0000NN_*.php       # 10 tablas (ver data-model.md)
├── factories/                               # una por modelo nuevo
└── seeders/{RolesYPermisosSeeder (ampliado), EscalasCalificacionSeeder, AcademicoDemoSeeder}.php
deploy/deploy.sh                             # + sge:sincronizar-base tras migrate
resources/views/livewire/academico/…         # una vista por componente
routes/web.php                               # grupo /academico
tests/{Feature/Academico/**, Unit/Academico/**}
```

**Structure Decision**: monolito Laravel existente; el dominio se agrupa en `app/Academico` (servicios), `app/Livewire/Academico` (UI) y `app/Models`, igual que el Módulo 1 agrupa `Admin/*`. No se crean proyectos ni capas nuevas.

## Complexity Tracking

Sin violaciones de la Constitución; no se requiere justificación.
