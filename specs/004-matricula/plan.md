# Implementation Plan: Matrícula

**Branch**: `004-matricula` | **Date**: 2026-11-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-matricula/spec.md`

**Módulo MVP**: 3 — Matrícula (Constitución, Principio IV)

## Summary

Registro genérico de alumnos, vínculos con su usuario y con sus apoderados, y matrícula individual y masiva
en secciones del año vigente, con estados (matriculado, retirado, trasladado) e historial. Se implementa con
las convenciones del Módulo 2: modelos Eloquent, componentes Livewire de clase, autorización por permisos
(`Gate::before` contra `rol_permiso`) y filtros de pertenencia (`RequierePertenencia`) para Docente (nómina de
sus secciones), Padre de Familia (sus hijos) y Alumno (su ficha). Las reglas con riesgo de concurrencia
(documento único por tipo y número; una matrícula activa por alumno y año) se protegen con índices únicos
—incluida una columna generada para «activa»— más transacciones con `lockForUpdate`. Quién es alumno o
apoderado se decide por permisos marcadores, sin nombres de rol. Los permisos y el catálogo de tipos de
documento llegan a staging con `sge:sincronizar-base` (ampliado). Los datos de ejemplo son ficticios y el
seeder rechaza producción (Ley 29733).

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13

**Primary Dependencies**: Livewire 4 (componentes de clase), Tailwind 4 / Vite (ya presentes). Sin paquetes nuevos.

**Storage**: MySQL 8 (`utf8mb4_unicode_ci`: búsqueda sin distinguir mayúsculas ni tildes). Columna generada almacenada para la unicidad de la matrícula activa. Redis sin cambios.

**Testing**: Pest 4 (Feature con `RefreshDatabase` sobre MySQL `sge_testing`; Unit sin base de datos). Pruebas de concurrencia con transacciones; prueba de volumen para SC-005.

**Target Platform**: Docker Compose, nginx + PHP-FPM; staging en Azure.

**Project Type**: Aplicación web monolítica Laravel + Blade + Livewire (sin API separada).

**Performance Goals**: SC-005 — con 2000 alumnos y 200 secciones, primera página de listado, nómina y búsqueda < 2 s y número de consultas constante.

**Constraints**: Sin nombres de rol ni de institución, nivel, grado o sección en el código; listados paginados con `with()`; índices en toda FK y columna de búsqueda/filtro/orden; sin colas; datos personales de menores solo para quien tiene permiso y nunca en errores ni registros.

**Scale/Scope**: Una institución; ~2000 alumnos; ~8 pantallas Livewire.

## Constitution Check

*GATE: pasa antes de la investigación; se reevalúa tras el diseño (resultado abajo).*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, MySQL 8, sin servicios nuevos. | ✅ |
| II. Genérico y configurable | Tipos de documento y sus reglas de formato son una tabla de datos base; sección, grado y año vienen del Módulo 2. Solo son estructurales en código el parentesco, el sexo y los estados de matrícula (enums de dominio, como `TipoPeriodo`). Nada de ejemplo en código. | ✅ |
| III. RBAC por tablas | Seis permisos nuevos en `rol_permiso`; alumno/apoderado por permiso marcador; pertenencia con reglas registradas en `config/pertenencia.php` evaluadas por permisos, no por rol. `SinCondicionalesDeRolTest` sigue en verde. | ✅ |
| IV. Alcance MVP | Módulo 3. Excluidos: asistencia, notas, pagos, aforo y promoción (exclusiones deliberadas de la spec). | ✅ |
| V. Pruebas | Cada regla de autorización y validación con Feature test permitido/denegado o válido/inválido (FR-029), más concurrencia y volumen. k6 no aplica: ningún endpoint es crítico (Constitución 2.2.0: la carga va en el Módulo 5). Verificación manual: prueba de humo de ~10 min. | ✅ |
| VI. IA restringida | Sin chatbot aquí; `Alumno::visiblePara()` y `Matricula::visiblePara()` quedan listos para el Módulo 6. | ✅ N/A |
| VII. Simplicidad | Eloquent + Livewire + dos servicios con transacciones (precedente: `AsignacionService`). Sin repositorios, colas ni caché. | ✅ |
| Rendimiento | Índices en FK y filtros, `with()`, `paginate(15)` en todo listado. | ✅ |
| Datos personales (Ley 29733, FR-025/026) | Seeders ficticios con rechazo en producción; sin datos de alumnos en errores/registros; mínimo visible por rol (Docente: nombres, apellidos, documento). | ✅ |

**Re-evaluación tras Fase 1**: sin cambios; no hay violaciones, la tabla de complejidad queda vacía.

## Project Structure

### Documentation (this feature)

```text
specs/004-matricula/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rutas-web.md
│   └── permisos-y-pertenencia.md
├── checklists/requirements.md
└── tasks.md              # /speckit-tasks (no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Matricula/
│   ├── AlumnoService.php             # alta/edición, desactivar/eliminar, vincular usuario y apoderados
│   ├── MatriculaService.php          # matricular, masiva (todo o nada), cambiar estado, rematricular
│   ├── EstadoMatricula.php           # enum matriculado|retirado|trasladado
│   ├── Parentesco.php                # enum madre|padre|tutor|otro
│   └── Sexo.php                      # enum femenino|masculino
├── Autorizacion/Pertenencia/Reglas/{AlumnoRegla,MatriculaRegla}.php
├── Livewire/Matricula/
│   ├── Alumnos/{Index,Form,Show}.php         # Show: datos, apoderados, historial, vínculos
│   ├── Nominas/{Index,Show}.php              # secciones visibles y nómina de una sección (+ matrícula masiva)
│   └── MisAlumnos/Index.php                  # Padre de Familia y Alumno
├── Models/{TipoDocumento,Alumno,Matricula}.php   # + pivote alumno_apoderado
└── Console/Commands/SincronizarBase.php          # + TiposDocumentoSeeder

config/{menu.php, pertenencia.php}           # ítems y reglas registradas
database/
├── migrations/2026_11_05_0000NN_*.php       # 4 tablas (ver data-model.md)
├── factories/                               # TipoDocumento, Alumno, Matricula
└── seeders/{RolesYPermisosSeeder (ampliado), TiposDocumentoSeeder, MatriculaDemoSeeder}.php
resources/views/livewire/matricula/…         # una vista por componente
routes/web.php                               # grupo /matricula
tests/{Feature/Matricula/**, Unit/Matricula/**}
```

**Structure Decision**: monolito Laravel existente; el dominio se agrupa en `app/Matricula` (servicios),
`app/Livewire/Matricula` (UI) y `app/Models`, igual que `app/Academico` en el Módulo 2.

## Complexity Tracking

Sin violaciones de la Constitución; no se requiere justificación.
