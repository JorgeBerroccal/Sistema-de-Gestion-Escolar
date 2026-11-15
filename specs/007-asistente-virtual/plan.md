# Implementation Plan: Asistente Virtual

**Branch**: `007-asistente-virtual` | **Date**: 2026-11-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-asistente-virtual/spec.md`

**Módulo MVP**: 6 — Asistente Virtual (Constitución, Principios IV y VI; versión 2.3.0)

## Summary

Un chat en español, disponible con el permiso nuevo `asistente.usar` (Padre de Familia y Alumno por defecto),
que responde sobre notas publicadas, asistencia, matrícula y sección. Para cada pregunta, el servidor (1) comprueba
disponibilidad (falla cerrado en producción si el adaptador no admite datos reales), (2) aplica el límite de
solicitudes por usuario y el global del plan gratuito, (3) resuelve a qué alumno se refiere la pregunta entre los
propios o hijos vinculados, (4) construye un contexto mínimo reutilizando `ConsultaNotas`, `ResumenAsistencia` y
`MatriculaRegla::alumnosPropios` (las mismas consultas filtradas de las pantallas) y (5) llama al proveedor a
través del puerto `ProveedorIA`. Adaptadores: `ProveedorFalso` (pruebas y desarrollo) y `ProveedorOpenRouter`
(modelo y lista de respaldo por variable de entorno). No hay tablas nuevas ni dependencias nuevas: la conversación
vive en el estado del componente Livewire y no se persiste. Gemini queda documentado como plan B.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13

**Primary Dependencies**: Livewire 4, Tailwind 4 / Vite (presentes). **Sin paquetes nuevos**: OpenRouter se llama con el cliente HTTP de Laravel (`Http`).

**Storage**: Ninguna tabla nueva. Un permiso nuevo en `permisos`/`rol_permiso` (seeder). Contadores del limitador en Redis (ya presente, mismo mecanismo que el login). La conversación no se guarda.

**Testing**: Pest 4 (Feature con `RefreshDatabase`; Unit para el constructor de contexto, el resolvedor de alumno y la configuración de adaptadores). Todo con `ProveedorFalso` y `Http::fake()` con `Http::preventStrayRequests()`: ninguna prueba sale a la red.

**Target Platform**: Docker Compose, nginx + PHP-FPM; staging en Azure.

**Project Type**: Aplicación web monolítica Laravel + Blade + Livewire (sin API separada).

**Performance Goals**: Sin criterio cronometrado (Constitución 2.2.0). El contexto se arma con consultas acotadas (`with()`, sin N+1); el tope de tiempo de espera al proveedor es configurable (por defecto 20 s) para no bloquear workers de PHP-FPM.

**Constraints**: Sin nombres de rol en código (el acceso es por permiso); sin historial; el contenido de mensajes y respuestas no se registra en logs (solo metadatos: usuario, adaptador, resultado); límite global ≤ 50/día y 20/minuto (plan gratuito) con márgenes por configuración; claves solo en `.env`.

**Scale/Scope**: Una institución; uso ocasional por familias; ~1 pantalla Livewire, ~7 clases de servicio, 1 permiso, 1 ítem de menú.

## Constitution Check

*GATE: pasa antes de la investigación; se reevalúa tras el diseño (resultado abajo).*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Stack fijo | Laravel 13 / PHP 8.3, Blade + Livewire, Redis para el limitador. Sin SDK ni servicio nuevo: cliente HTTP nativo. | ✅ |
| II. Genérico y configurable | Nada de nombres de curso, periodo, rol o institución en código; proveedor, modelo, respaldo y límites son configuración. | ✅ |
| III. RBAC por tablas | Permiso nuevo `asistente.usar` en `rol_permiso`; el contexto parte de `MatriculaRegla::alumnosPropios` (vínculos del Módulo 3) y de periodos publicados (`CierrePeriodo`). `SinCondicionalesDeRolTest` sigue en verde. | ✅ |
| IV. Alcance MVP | Módulo 6. Excluidos: historial, voz, adjuntos, acciones de escritura, Gemini, otros roles. | ✅ |
| V. Pruebas | Feature tests permitido/denegado por permiso; pruebas de acceso ajeno, de no publicado, de minimización, de falla cerrado, de límite y de proveedor caído, todas con adaptador falso. Prueba de humo de ~10 min. Sin k6 (2.2.0). | ✅ |
| VI. IA restringida (2.3.0) | Puerto con adaptadores intercambiables por entorno; adaptador falso; la suite no llama a servicios externos; cada adaptador declara `admiteDatosReales()`; falla cerrado en producción; el contexto sale del backend con las consultas filtradas; claves por entorno; Feature tests de acceso ajeno. | ✅ |
| VII. Simplicidad | Sin tablas, colas, caché propia ni paquete nuevo. Una interfaz con dos implementaciones, un constructor de contexto y un servicio de orquestación. Sin repositorios. | ✅ |
| Rendimiento | Consultas con `with()`; sin listados; el límite evita ráfagas. | ✅ |
| Datos personales (Ley 29733) | Minimización (sin documento, fecha de nacimiento ni correo); staging solo ficticio; en producción no se usa un adaptador gratuito (falla cerrado). | ✅ |

**Re-evaluación tras Fase 1**: sin cambios; ninguna entrada en la tabla de complejidad.

## Project Structure

### Documentation (this feature)

```text
specs/007-asistente-virtual/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── proveedor-ia.md
│   └── chat.md
├── checklists/requirements.md
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/Asistente/
├── Proveedores/
│   ├── ProveedorIA.php            # puerto: responder(), admiteDatosReales(), nombre()
│   ├── RespuestaIA.php            # texto + modelo usado
│   ├── ProveedorFalso.php         # determinista; registra lo recibido para las pruebas
│   ├── ProveedorOpenRouter.php    # Http + lista de modelos de respaldo
│   └── ProveedorNoDisponible.php  # excepción única hacia la capa superior
├── AsistenteService.php           # orquesta: disponibilidad → límite → alumno → contexto → proveedor
├── Disponibilidad.php             # falla cerrado (APP_ENV production + adaptador sin datos reales)
├── LimiteSolicitudes.php          # por usuario y global, sobre RateLimiter
├── ResolverAlumno.php             # a qué alumno propio se refiere el mensaje (sin IA)
├── ConstructorContexto.php        # contexto mínimo: matrícula, notas publicadas, asistencia
└── Instrucciones.php              # mensaje de sistema (español, solo contexto, sin inventar)
app/Livewire/Asistente/Chat/Index.php
resources/views/livewire/asistente/chat/index.blade.php
config/asistente.php               # proveedor, openrouter.*, limites.*, contexto.*
config/menu.php                    # + ítem «Asistente virtual»
database/seeders/RolesYPermisosSeeder.php   # + asistente.usar → Padre de Familia, Alumno
routes/web.php                     # + GET /asistente
.env.example                       # + variables ASISTENTE_*
docs/despliegue/                   # + variables de staging; plan B Gemini documentado
tests/Unit/Asistente/              # contexto, resolvedor, disponibilidad, configuración
tests/Feature/Asistente/           # acceso, ajeno, no publicado, minimización, límite, caída, producción
```

**Structure Decision**: sigue el patrón de los Módulos 3–5 (`app/<Modulo>/` para servicios, `app/Livewire/<Modulo>/` para pantallas, regla en `config/`). Al no haber datos propios, no hay modelos ni migraciones.

## Complexity Tracking

Sin violaciones de la constitución; no hay entradas.
