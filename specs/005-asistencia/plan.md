# Implementation Plan: Asistencia

**Branch**: `005-asistencia` | **Date**: 2026-11-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-asistencia/spec.md`

**Módulo MVP**: 4 — Asistencia (Constitución, Principio IV)

## Summary

Registro de asistencia diaria por sección sobre las matrículas del Módulo 3 y las asignaciones del Módulo 2:
el Docente (o el Administrador) toma la lista de una fecha, justifica faltas; el Alumno y el Padre de Familia
consultan lo propio por mes con resumen; Administrador, Director y Docente obtienen reportes por sección y
rango o periodo de evaluación. Se sigue la receta de los módulos anteriores: modelos Eloquent, componentes
Livewire de clase, autorización con `Gate::before` contra `rol_permiso` y pertenencia por reglas registradas
(`config/pertenencia.php`). Los estados de asistencia son un catálogo en tabla (FR-002), con banderas que dicen
cuáles cuentan como asistencia y a qué estado pasa una falta al justificarse. La unicidad (sección, alumno,
fecha) la impone un índice único y el guardado de la lista es un `upsert` dentro de una transacción
(todo o nada, sin duplicados bajo concurrencia). La regla de fin de semana es fija (sábado y domingo) y vive en
un único servicio. Sin paquetes nuevos, colas ni caché.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13

**Primary Dependencies**: Livewire 4 (componentes de clase), Tailwind 4 / Vite (ya presentes). Sin paquetes nuevos.

**Storage**: MySQL 8 (`utf8mb4_unicode_ci`). Dos tablas nuevas y `config/asistencia.php` (zona horaria de la institución): `estados_asistencia` (datos base) y `asistencias`. Redis sin cambios.

**Testing**: Pest 4 (Feature con `RefreshDatabase` sobre MySQL `sge_testing`; Unit sin base de datos). Pruebas de concurrencia y de volumen para SC-005.

**Target Platform**: Docker Compose, nginx + PHP-FPM; staging en Azure.

**Project Type**: Aplicación web monolítica Laravel + Blade + Livewire (sin API separada).

**Performance Goals**: SC-005 — reporte de una sección con un año de registros (~30 alumnos × ~200 días) en < 3 s; consultas constantes (agregación en SQL, no en PHP).

**Constraints**: Sin nombres de rol ni de institución/nivel/sección en código; listados paginados con `with()`; índices en toda FK y columna de filtro; sin colas; datos de menores solo para quien tiene permiso y fuera de errores y registros.

**Scale/Scope**: Una institución; ~2000 alumnos; ~5 pantallas Livewire.

## Constitution Check

*GATE: pasa antes de la investigación; se reevalúa tras el diseño (resultado abajo).*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, MySQL 8, sin servicios nuevos. | ✅ |
| II. Genérico y configurable | Los estados de asistencia son una tabla de datos base; secciones, años y periodos vienen de los Módulos 2–3. Nada de ejemplo en código. La regla sábado/domingo es una regla de calendario, no un dato de institución. | ✅ |
| III. RBAC por tablas | Cuatro permisos nuevos en `rol_permiso`; quién es docente se decide con el marcador existente `docencia.dictar` y las asignaciones; pertenencia por `AsistenciaRegla`. `SinCondicionalesDeRolTest` sigue en verde. | ✅ |
| IV. Alcance MVP | Módulo 4. Excluidos: asistencia por curso, feriados, notificaciones, notas (exclusiones deliberadas de la spec). | ✅ |
| V. Pruebas | Cada regla de autorización con Feature test permitido/denegado (FR-013), más concurrencia y volumen. k6 no aplica: ningún endpoint crítico (Constitución 2.2.0). Verificación manual: prueba de humo de ~10 min. | ✅ |
| VI. IA restringida | Sin chatbot aquí; `Asistencia::visiblePara()` queda listo para el Módulo 6. | ✅ N/A |
| VII. Simplicidad | Eloquent + Livewire + un servicio con transacciones (precedente: `MatriculaService`) y un servicio de consulta con agregados SQL. Sin repositorios, colas ni caché. | ✅ |
| Rendimiento | Índices en FK y filtros, `with()`, `paginate()` en listados; reportes con `GROUP BY`. | ✅ |
| Datos personales (Ley 29733, FR-015) | Seeder ficticio que rechaza producción; sin nombres ni documentos en errores o registros. | ✅ |

**Re-evaluación tras Fase 1**: sin cambios; no hay violaciones, la tabla de complejidad queda vacía.

## Project Structure

### Documentation (this feature)

```text
specs/005-asistencia/
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
├── Asistencia/
│   ├── AsistenciaService.php         # lista de una fecha, guardar (todo o nada), justificar/revertir, reglas de fecha
│   └── ResumenAsistencia.php         # consultas agregadas: resumen por alumno, mes, sección y ranking
├── Autorizacion/Pertenencia/Reglas/AsistenciaRegla.php
├── Livewire/Asistencia/
│   ├── Tomar/{Index,Show}.php        # secciones tomables y lista de una fecha (+ justificar)
│   ├── Propia/Index.php              # Alumno y Padre de Familia: hijo, mes y resumen
│   └── Reportes/{Index,Alumno}.php   # reporte por sección y detalle de un alumno
├── Models/{EstadoAsistencia,Asistencia}.php
└── Console/Commands/SincronizarBase.php   # + EstadosAsistenciaSeeder

config/{menu.php, pertenencia.php}        # ítems y regla registrados
database/
├── migrations/2026_11_06_0000NN_*.php    # 2 tablas (ver data-model.md)
├── factories/                            # EstadoAsistencia, Asistencia
└── seeders/{RolesYPermisosSeeder (ampliado), EstadosAsistenciaSeeder, AsistenciaDemoSeeder}.php
resources/views/livewire/asistencia/…     # una vista por componente
routes/web.php                            # grupo /asistencia
tests/{Feature/Asistencia/**, Unit/Asistencia/**}
```

**Structure Decision**: monolito Laravel existente; el dominio se agrupa en `app/Asistencia`, la UI en
`app/Livewire/Asistencia` y los modelos en `app/Models`, igual que `app/Matricula` en el Módulo 3.

## Complexity Tracking

Sin violaciones de la Constitución. Una excepción acotada a la regla «todo listado paginado», justificada aquí:

| Elemento | Por qué no se pagina | Cota |
|---|---|---|
| Lista de la fecha en `Tomar\Show` | FR-006: la lista de una sección se guarda en una sola operación (todo o nada) y el Docente necesita ver a todos los alumnos a la vez. | Una sola sección (decenas de alumnos), nunca la institución. |
| Selector de secciones de `Reportes\Index` | Es un `<select>`, no un listado; el ranking y el detalle sí se paginan. | `limit(200)`. |
| Detalle mensual de `Propia\Index` | Un mes calendario. | A lo más 31 filas por alumno; hijos `limit(50)`. |
