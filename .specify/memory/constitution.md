# Constitución del Sistema de Gestión Escolar (SGE)

## Core Principles

### I. Stack Tecnológico Fijo

- El backend MUST implementarse en Laravel 13 sobre PHP 8.3 o superior.
- El frontend MUST construirse con Blade y Livewire. Está prohibido introducir frameworks de
  frontend separados (React, Vue, Angular, Svelte u otros SPA).
- La base de datos MUST ser MySQL 8.
- Redis MUST usarse como almacén de sesiones, caché y limitador de intentos (rate limiting). Es un
  servicio de infraestructura (no código propio), por lo que no contraviene la prohibición de
  servicios escritos en otro lenguaje.
- En producción, la aplicación MUST servirse con nginx + PHP-FPM, con OPcache activo y
  `php artisan optimize` ejecutado en cada despliegue. Laravel Octane queda descartado.
- El entorno de desarrollo y despliegue MUST definirse con Docker y Docker Compose.
- Está prohibido introducir otros lenguajes de backend o servicios adicionales escritos en otro
  lenguaje.

**Rationale**: Un stack único y conocido reduce la curva de aprendizaje, la superficie de
integración y el riesgo de no cumplir el plazo fijo de 2 meses.

### II. Diseño Genérico y Configurable

- El sistema MUST poder adoptarse por cualquier institución educativa pública o privada de
  primaria y/o secundaria del Perú, no por un colegio específico.
- La institución, sus niveles, grados, secciones y cursos MUST modelarse como entidades de
  configuración persistidas en tablas.
- Está prohibido codificar en el código fuente (constantes, enums, condicionales, vistas) nombres
  de instituciones, niveles, grados, secciones o cursos concretos. Los datos iniciales solo pueden
  existir en seeders de ejemplo.

**Rationale**: La genericidad es un requisito arquitectónico evaluable; los valores fijos en el
código impiden reutilizar el sistema en otra institución sin modificarlo.

### III. Control de Acceso Basado en Tablas (RBAC)

- Desde el primer módulo MUST existir un modelo genérico de roles y permisos con las tablas
  Rol, Permiso, Rol_Permiso y Usuario.
- Toda autorización MUST verificarse contra la tabla Rol_Permiso (p. ej. mediante Gates/Policies
  de Laravel que consulten el permiso requerido).
- Está prohibido escribir validaciones de acceso como condicionales fijos por nombre de rol
  (por ejemplo `if ($user->role == 'docente')`) en controladores, componentes Livewire, vistas,
  middleware o servicios.
- Los roles iniciales, cargados vía seeder, son: Administrador, Director, Docente, Padre de
  Familia y Alumno. Agregar o modificar roles MUST ser posible solo con datos, sin cambiar código.
- Las restricciones de pertenencia de datos (p. ej. un padre solo ve a sus hijos, un docente solo
  sus secciones) MUST aplicarse en consultas/policies, además del chequeo de permiso.

**Rationale**: Un RBAC basado en datos es la base de seguridad de todos los módulos y permite
cambiar políticas de acceso sin redeploy.

### IV. Alcance Fijo del MVP (6 Módulos)

El alcance del MVP se limita exactamente a estos módulos:

1. Autenticación y Roles
2. Gestión Académica Base
3. Matrícula
4. Asistencia
5. Evaluación y Boletas
6. Asistente Virtual (Chatbot IA)

- Está prohibido agregar funcionalidad fuera de estos 6 módulos sin aprobación explícita del
  desarrollador responsable, registrada en la especificación correspondiente.
- Toda especificación o plan MUST indicar a cuál de los 6 módulos pertenece.

**Rationale**: El plazo de 2 meses es inamovible; el control de alcance es la principal defensa
contra el retraso.

### V. Pruebas Obligatorias (NON-NEGOTIABLE)

- Ninguna tarea se considera terminada sin sus pruebas unitarias (Pest/PHPUnit) y sus pruebas de
  integración (Feature tests) correspondientes, todas en verde.
- Toda regla de autorización MUST tener al menos un Feature test que verifique acceso permitido y
  acceso denegado.
- Los endpoints críticos — login, registro de notas y consulta de boletas — MUST además contar con
  pruebas de carga con k6 ejecutadas y documentadas antes de considerarse listos para despliegue.

**Rationale**: Las pruebas son la evidencia verificable de calidad en un proyecto académico y
previenen regresiones en un calendario sin margen para corregirlas tarde.

### VI. Asistente IA Restringido por RBAC

- El chatbot MUST usar la API de Claude con un modelo de la familia Haiku, por costo.
- El chatbot MUST responder únicamente con información a la que el usuario autenticado tiene
  acceso según su rol y sus relaciones de pertenencia (Principio III).
- Los datos enviados al modelo como contexto MUST obtenerse en el backend aplicando las mismas
  policies/consultas filtradas del resto del sistema; el modelo nunca decide qué datos puede ver.
- Está prohibido exponer datos de otros estudiantes. Este comportamiento MUST cubrirse con
  Feature tests que intenten acceder a datos ajenos.
- La API key MUST gestionarse por variables de entorno y nunca versionarse.

**Rationale**: Un LLM no es una frontera de seguridad; el filtrado debe ocurrir antes de construir
el prompt para garantizar la privacidad de los estudiantes.

### VII. Simplicidad

- Ante cualquier decisión de diseño MUST elegirse la solución más simple que cumpla el
  requerimiento.
- Se prefieren las convenciones y herramientas nativas de Laravel (Eloquent, Gates/Policies,
  Form Requests, Livewire) antes que capas o patrones adicionales (repositorios genéricos, CQRS,
  microservicios, colas, cachés distribuidas) salvo necesidad demostrada. Redis para sesiones,
  caché y limitador de intentos (Principio I) es la necesidad demostrada por el requisito de
  1000 usuarios virtuales; no autoriza por sí solo el uso de colas.
- Toda complejidad adicional MUST justificarse por escrito en la sección de complejidad del plan.

**Rationale**: Es un proyecto académico con plazo fijo; la sobre-ingeniería consume tiempo que no
existe.

## Restricciones Técnicas y de Plazo

- Plazo total del MVP: 2 meses, fijo.
- Idioma de la interfaz y del dominio: español (Perú).
- Configuración sensible (credenciales de BD, API key de Claude) MUST residir en `.env` y no en el
  repositorio; MUST existir un `.env.example` actualizado.
- El proyecto MUST levantarse completo con `docker compose up` sin pasos manuales no documentados.
- Migraciones y seeders MUST permitir reconstruir la base de datos desde cero, incluyendo los roles
  y permisos iniciales.

## Reglas de Rendimiento (aplican a todos los módulos)

- Toda columna usada para búsqueda, filtrado u ordenamiento y toda clave foránea MUST tener índice.
- Las relaciones Eloquent usadas en una vista o respuesta MUST cargarse con `with()` (carga
  anticipada) para evitar consultas N+1.
- Todo listado MUST estar paginado; está prohibido cargar colecciones completas sin límite.

**Rationale**: El sistema debe sostener 1000 usuarios virtuales en un VPS de 2 vCPU / 4 GB; estas
reglas son baratas de aplicar desde el inicio y costosas de corregir después.

## Flujo de Desarrollo y Criterios de Terminado

- Cada funcionalidad sigue el flujo Spec Kit: especificación → plan → tareas → implementación.
- El plan de cada funcionalidad MUST incluir un "Constitution Check" que verifique los Principios
  I–VII antes de implementar.
- Una tarea está terminada (Definition of Done) solo si:
  1. Pertenece a uno de los 6 módulos del MVP.
  2. No contiene validaciones de acceso por nombre de rol ni valores de institución fijos en código.
  3. Tiene pruebas unitarias y Feature tests en verde.
  4. Si toca un endpoint crítico, tiene su prueba de carga k6 ejecutada.
- Las revisiones de código MUST rechazar cambios que violen cualquier principio.

## Governance

- Esta constitución prevalece sobre cualquier otra práctica, guía o preferencia del proyecto.
- Enmiendas: solo el desarrollador responsable del proyecto puede aprobarlas; cada enmienda MUST
  documentarse en este archivo con su justificación y actualizar la versión y la fecha.
- Versionado semántico de la constitución:
  - MAJOR: eliminación o redefinición incompatible de un principio.
  - MINOR: nuevo principio o sección, o ampliación material de una guía.
  - PATCH: aclaraciones, redacción o correcciones sin cambio semántico.
- Cumplimiento: todo plan, revisión y tarea MUST verificar conformidad con esta constitución;
  cualquier desviación MUST quedar justificada en la sección de complejidad del plan o ser
  rechazada.
- Historial de enmiendas:
  - 2.0.0 (2026-09-27): Principio I — la versión obligatoria del framework cambia de Laravel 11 a
    Laravel 13. Justificación: Laravel 11 dejó de recibir parches de seguridad en marzo de 2026 y
    Laravel 12 dejó de recibir correcciones de errores en agosto de 2026; Laravel 13 (marzo de
    2026) es la versión con soporte completo y mantiene el requisito de PHP 8.3+. Es MAJOR porque
    redefine de forma incompatible una regla del Principio I: código en Laravel 11 deja de cumplir.
  - 2.1.0 (2026-10-12): Principio I — se agrega Redis (sesiones, caché, limitador de intentos) y
    la pila de producción nginx + PHP-FPM con OPcache y `php artisan optimize` (Octane
    descartado); Principio VII — aclaración de que Redis es la necesidad demostrada, sin habilitar
    colas; nueva sección "Reglas de Rendimiento" (índices, `with()`, paginación obligatoria).
    Justificación: la prueba de carga debe llegar a 1000 usuarios virtuales en un VPS de 2 vCPU /
    4 GB. Es MINOR porque amplía materialmente guías sin invalidar código conforme a 2.0.0.

**Version**: 2.1.0 | **Ratified**: 2026-09-27 | **Last Amended**: 2026-10-12
