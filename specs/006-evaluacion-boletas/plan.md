# Implementation Plan: Evaluación y Boletas

**Branch**: `006-evaluacion-boletas` | **Date**: 2026-11-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-evaluacion-boletas/spec.md`

**Módulo MVP**: 5 — Evaluación y Boletas (Constitución, Principio IV)

## Summary

El Docente define, por curso, sección y periodo, criterios de evaluación con peso (suman 100 %) y registra las
notas de la nómina en una sola operación todo o nada; el Administrador cierra y reabre periodos por sección
(el cierre bloquea al Docente y publica notas y boleta a Alumnos y Padres); Alumno y Padre consultan lo propio
de periodos publicados; se genera una boleta PDF de un único formato (periodo o anual) con el resumen de
asistencia del Módulo 4; Administrador, Director y Docente obtienen el reporte de notas por sección. Se sigue la
receta de los módulos anteriores: modelos Eloquent, componentes Livewire de clase, autorización con `Gate::before`
contra `rol_permiso` y pertenencia por reglas registradas (`config/pertenencia.php`). El cálculo de promedios
vive en una clase pura (`CalculoPromedios`) sin base de datos, para probar la escala literal y las notas vacías
de forma aislada. El único paquete nuevo es `barryvdh/laravel-dompdf` (PHP puro; ver Complejidad). La sesión
única de carga k6 de la Constitución 2.2.0 se ejecuta en este módulo y cubre login, registro de notas y
consulta de boletas. El guardado de notas es un formulario clásico (POST con CSRF a un controlador), como el login, y Livewire se
limita a la interacción de la pantalla; así k6 lo prueba sin depender del protocolo interno de Livewire.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13

**Primary Dependencies**: Livewire 4, Tailwind 4 / Vite (presentes) y **`barryvdh/laravel-dompdf`** (nuevo, solo PDF de la boleta).

**Storage**: MySQL 8 (`utf8mb4_unicode_ci`). Tres tablas nuevas (`criterios_evaluacion`, `notas`, `cierres_periodo`) y dos columnas nuevas en `escalas_calificacion` (`equivalentes`, `umbral_aprobacion`). Redis sin cambios; sin colas (`QUEUE_CONNECTION=sync`).

**Testing**: Pest 4 (Feature con `RefreshDatabase` sobre MySQL `sge_testing`; Unit sin base de datos para `CalculoPromedios`). Pruebas de concurrencia, de volumen y de genericidad. k6 en sesión única (`tests/Load/evaluacion.js`).

**Target Platform**: Docker Compose, nginx + PHP-FPM; staging en Azure.

**Project Type**: Aplicación web monolítica Laravel + Blade + Livewire (sin API separada). La boleta es una ruta GET que devuelve el PDF.

**Performance Goals**: SC-006 — boleta generada y descargada en < 5 s; SC-001 — 30 alumnos × 5 criterios guardados en una operación (un `upsert` por lotes dentro de una transacción); SC-005 — k6: error < 1 %, umbrales por perfil en `contracts/prueba-de-carga.md` (login p95 < 3,5 s heredado del Módulo 1; boleta p95 < 5 s en `diario` y < 8 s en el pico de `carga`).

**Constraints**: Sin nombres de rol ni de institución/curso/periodo/criterio en código; listados paginados con `with()`; índice en toda FK y columna de filtro; sin colas ni caché; datos de menores fuera de errores y registros; la boleta no se guarda en disco (se genera al pedirla).

**Scale/Scope**: Una institución; ~2000 alumnos; 1000 usuarios virtuales en la sesión k6; ~6 pantallas Livewire y una ruta PDF.

## Constitution Check

*GATE: pasa antes de la investigación; se reevalúa tras el diseño (resultado abajo).*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, MySQL 8, Redis sin cambios. Dompdf es una librería PHP (no un servicio ni otro lenguaje); se descarta Chromium/wkhtmltopdf por ser binarios externos. | ✅ |
| II. Genérico y configurable | Criterios, periodos y cursos son datos. Escala, equivalentes numéricos y umbral viven en `escalas_calificacion` (no en código). Nada de ejemplo fuera de seeders. | ✅ |
| III. RBAC por tablas | Siete permisos nuevos en `rol_permiso`; docente = marcador `docencia.dictar` + asignaciones; familia = vínculos del Módulo 3. Pertenencia por `NotaRegla` y `CriterioRegla`. `SinCondicionalesDeRolTest` sigue en verde. | ✅ |
| IV. Alcance MVP | Módulo 5. Excluidos: conducta, recuperaciones, Excel, plantillas múltiples, firma, notificaciones, chatbot. | ✅ |
| V. Pruebas | Cada regla de autorización con Feature test permitido/denegado; unitarias del cálculo; concurrencia y volumen. **Sesión única k6** de este módulo para login, registro de notas y boletas (Constitución 2.2.0). Prueba de humo de ~10 min. | ✅ |
| VI. IA restringida | Sin chatbot aquí; `Nota::visiblePara()` y el cálculo quedan listos para el Módulo 6. | ✅ N/A |
| VII. Simplicidad | Eloquent + Livewire + dos servicios (`EvaluacionService` con transacciones, `CalculoPromedios` puro). Sin repositorios, colas ni caché. Una dependencia nueva, justificada abajo. | ✅ |
| Rendimiento | Índices en FK y filtros, `with()`, `paginate()` en listados; el guardado de notas es un solo `upsert`. | ✅ |
| Datos personales (Ley 29733, FR-017) | Seeder ficticio que rechaza producción; cuentas de carga solo local/staging/testing y desactivadas fuera de la sesión k6. | ✅ |

**Re-evaluación tras Fase 1**: sin cambios; la única entrada de complejidad es Dompdf y la tabla la justifica.

## Project Structure

### Documentation (this feature)

```text
specs/006-evaluacion-boletas/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rutas-web.md
│   ├── permisos-y-pertenencia.md
│   └── prueba-de-carga.md
├── checklists/requirements.md
└── tasks.md              # /speckit-tasks (no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Evaluacion/
│   ├── CalculoPromedios.php        # puro: ponderado, renormalización, escala literal, redondeo, anual y validación del valor contra la escala
│   ├── EvaluacionService.php       # esquema de criterios, guardar notas (todo o nada), cerrar/reabrir, reglas de escritura
│   ├── ConsultaNotas.php           # notas y promedios de un alumno / de una sección (con with(), acotado)
│   ├── BoletaService.php           # arma los datos de la boleta (notas + asistencia del Módulo 4) y el PDF
│   └── EvaluacionIncumplida.php    # excepción de dominio sin datos personales
├── Autorizacion/Pertenencia/Reglas/{NotaRegla,CriterioRegla}.php
├── Http/Controllers/{BoletaController,NotasController}.php   # GET del PDF y POST clásico del guardado de notas; autorizan antes de actuar
├── Livewire/Evaluacion/
│   ├── Registro/{Index,Show}.php   # cursos/secciones propios y tabla de notas (+ editor de criterios)
│   ├── Cierres/Index.php           # Administrador: cerrar y reabrir por sección y periodo
│   ├── Propia/Index.php            # Alumno y Padre: hijo, periodo, notas y enlace a la boleta
│   └── Reportes/Index.php          # promedios, orden de mérito y desaprobados por sección y periodo
├── Models/{CriterioEvaluacion,Nota,CierrePeriodo}.php   # + EscalaCalificacion ampliada
└── Console/Commands/SincronizarBase.php   # sin cambios de lista (la escala se actualiza en el seeder)

config/{menu.php, pertenencia.php}        # ítems y reglas registrados
database/
├── migrations/2026_11_13_0000NN_*.php    # 3 tablas + columnas de escala (ver data-model.md)
├── factories/                            # CriterioEvaluacion, Nota
└── seeders/{RolesYPermisosSeeder (ampliado), EscalasCalificacionSeeder (ampliado), EvaluacionDemoSeeder, EvaluacionCargaSeeder}.php
resources/views/{livewire/evaluacion/…, boletas/boleta.blade.php}
routes/web.php                            # grupo /notas y /boletas
tests/{Feature/Evaluacion/**, Unit/Evaluacion/**, Load/evaluacion.js}
docs/{evaluacion.md, despliegue/prueba-de-carga.md}   # módulo (escala como configuración) y carga con los flujos nuevos
```

**Structure Decision**: monolito Laravel existente; el dominio se agrupa en `app/Evaluacion`, la UI en
`app/Livewire/Evaluacion` y los modelos en `app/Models`, igual que `app/Asistencia` en el Módulo 4.

## Complexity Tracking

| Elemento | Por qué | Alternativa más simple descartada |
|---|---|---|
| Paquete `barryvdh/laravel-dompdf` | FR-011 exige un PDF descargable; no hay generador PDF en el framework. Es PHP puro: no añade servicio, binario ni lenguaje (Principio I). | HTML imprimible del navegador: no cumple «descargable en PDF» ni da el mismo documento a Padre y Docente. Chromium/wkhtmltopdf: binario externo en una imagen de 4 GB. |
| Excepción de paginación: tabla de notas de `Registro\Show` | El guardado es todo o nada (FR-004) y el Docente necesita ver a toda la sección a la vez. Cota: una sección × un curso × un periodo (decenas de alumnos, ≤ 10 criterios). | — |
| Excepción de paginación: selectores y detalle propio | `<select>` de secciones/cursos (`limit(200)`); en `Propia\Index` un hijo × un periodo (≤ 15 cursos), hijos `limit(50)`. El reporte por sección sí se pagina. | — |
