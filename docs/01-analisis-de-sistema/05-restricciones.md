# 05 - Restricciones

> Condiciones, reglas o limitaciones que deben respetarse durante el desarrollo del sistema. Pueden ser tecnológicas, organizacionales, legales o del proyecto.

Se conservan los IDs RC01–RC18 con la formulación vigente en la constitución del proyecto (`.specify/memory/constitution.md`) y la columna **Estado real**. Donde la restricción cambió respecto al análisis original, se indica la enmienda.

## Restricciones identificadas

| ID | Tipo | Restricción | Descripción | Estado real |
|----|------|-------------|-------------|-------------|
| RC01 | Tecnológica | Aplicación web responsive | El sistema debe ser accesible desde un navegador web, adaptable a celulares y tablets. | **Cumplida** como aplicación web con Blade, Livewire y Tailwind. La adaptación a celulares no se midió (AC08). |
| RC02 | Organizacional | Control de versiones | El código y la documentación deben gestionarse con Git y mantenerse en un repositorio de GitHub. | **Cumplida.** Repositorio en GitHub con ramas por módulo y pull requests integradas con merge commit. |
| RC03 | Tecnológica | Framework backend | El backend debe desarrollarse con **Laravel 13** sobre PHP 8.3 o superior. | **Modificada.** El análisis decía Laravel 11; la constitución 2.0.0 lo cambió a Laravel 13 porque Laravel 11 dejó de recibir parches de seguridad. `composer.json`: `laravel/framework ^13.17`, `php ^8.3`. |
| RC04 | Tecnológica | Interfaz sin frontend separado | La capa de presentación debe construirse con Blade y **Livewire 4** dentro del mismo proyecto Laravel (no se desarrollará una SPA separada). | **Cumplida.** `livewire/livewire ^4.2`; sin SPA. El guardado de la tabla de notas es un formulario HTML clásico con CSRF (spec 006, R-18). |
| RC05 | Tecnológica | Base de datos | La información debe almacenarse en MySQL 8, accedida mediante el ORM Eloquent y migraciones versionadas. | **Cumplida.** MySQL 8.0, Eloquent y 23 migraciones en `database/migrations/`. |
| RC06 | Tecnológica | Autenticación | La autenticación debe implementarse con un **login propio** y un modelo RBAC propio (tablas `roles`, `permisos` y `rol_permiso`). | **Modificada.** El análisis decía Laravel Breeze/Sanctum; no se usan. El login es un formulario HTTP clásico (`LoginController`, `LoginRequest`) y la autorización consulta `rol_permiso` (plan 001: sin starter kits ni `spatie/permission`). |
| RC07 | Tecnológica | Servicio de IA externo | El asistente virtual debe consumir el modelo de IA a través de una interfaz de proveedor (puerto) con adaptadores intercambiables, elegidos por variable de entorno. | **Modificada.** El análisis fijaba la API de Claude (Anthropic). La constitución 2.3.0 (Principio VI) generalizó la regla porque no hay presupuesto para créditos de Anthropic. Adaptadores: `ProveedorFalso` y `ProveedorOpenRouter`; Gemini queda documentado sin implementar. |
| RC08 | Tecnológica | Contenedores | La aplicación debe contenerizarse con Docker y Docker Compose. En producción se sirve con nginx + PHP-FPM, con OPcache y `php artisan optimize`; Laravel Octane queda descartado. | **Cumplida.** Cuatro servicios: `web` (nginx), `app` (PHP-FPM), `db` (MySQL 8) y `redis`. No se usa Laravel Sail. |
| RC09 | Económica | Infraestructura de bajo costo | El sistema debe funcionar en infraestructura de bajo costo. | **Modificada.** El análisis hablaba de un VPS o PaaS genérico. Se usó una VM de **Azure for Students**: `Standard_B2ats_v2` (2 vCPU, 1 GB) en el uso diario y `Standard_B2als_v2` (2 vCPU, 4 GB) solo en sesiones de carga. Ver ADR-009 y `docs/despliegue/costo-y-tamano.md`. |
| RC10 | Del proyecto | Plazo | El desarrollo tiene un plazo de dos meses, por lo que se limita a un MVP de 6 módulos. | **Cumplida.** Seis módulos en las specs 001, 003, 004, 005, 006 y 007; la spec 002 cubre el despliegue. |
| RC11 | Del proyecto | Metodología | El desarrollo debe seguir Spec-Driven Development: especificación → plan → tareas → implementación → verificación. | **Cumplida.** Spec Kit: cada módulo tiene `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`, `tasks.md` y `evidencias/` en `specs/`. |
| RC12 | Del proyecto | Pruebas | Deben incluirse pruebas unitarias y de integración con Pest y pruebas de carga con k6. | **Cumplida.** Pest 4 en `tests/Unit` y `tests/Feature`; k6 en `tests/Load/carga.js` y `tests/Load/evaluacion.js`, ejecutados en una sola sesión (constitución 2.2.0). Resultado de la carga: AC03. |
| RC13 | Del proyecto | Una institución por instalación | El sistema es configurable, pero cada instalación atiende a una sola institución (no es multi-tenant). | **Cumplida.** La tabla `instituciones` tiene una restricción `CHECK (id = 1)`: una sola ficha. |
| RC14 | Normativa | Evaluación por competencias | La evaluación debe ajustarse a la estructura del Currículo Nacional (competencias por curso, escala literal AD/A/B/C o vigesimal). | **Parcial.** Escala literal o vigesimal configurable. **No se modelan competencias**: el Docente define criterios con peso por curso, sección y periodo. Los equivalentes AD=4, A=3, B=2, C=1 y los umbrales son configuración de la institución, no una regla del Currículo Nacional (spec 006, R-03). |
| RC15 | Legal | Protección de datos personales | El tratamiento de datos de estudiantes (menores de edad) y apoderados debe respetar la Ley N.° 29733. | **Cumplida con medidas de ingeniería**: datos de ejemplo siempre ficticios y seeders que rechazan producción; sin documentos ni nombres en errores y registros; contexto mínimo hacia la IA; el adaptador con modelo gratuito no admite datos reales y falla cerrado en producción. No hay evaluación legal en el repositorio. |
| RC16 | Seguridad | HTTPS | En producción, la comunicación debe cifrarse con HTTPS (Let's Encrypt). | **Cumplida** con certbot en el servidor y nginx en el contenedor `web` (`docs/despliegue/certificado.md`; spec 002, D-05). |
| RC17 | Alcance | Fuera de alcance | No se implementarán gestión financiera, inscripciones online, mensajería interna ni certificados oficiales en esta versión. | **Cumplida.** |
| RC18 | Del proyecto | Asistente virtual fuera de la prueba de carga | En la prueba de carga el asistente virtual se excluye o responde con una respuesta simulada. | **Cumplida.** Los scripts de `tests/Load/` no llaman al asistente. Además, la suite automática usa solo `ProveedorFalso` o `Http::fake()`. |

## Restricciones de la constitución sin ID en el análisis original

La constitución del repositorio fija reglas adicionales que condicionan el diseño:

| Fuente | Restricción | Efecto en la arquitectura |
|--------|-------------|---------------------------|
| Principio II | Prohibido codificar nombres de instituciones, niveles, grados, secciones o cursos. | Estructura académica y escala como tablas (ADR-005). |
| Principio III | Toda autorización contra `rol_permiso`; prohibidas las validaciones por nombre de rol; la pertenencia se aplica en consultas. | Permisos por identificador y mecanismo de pertenencia fail-closed (ADR-003 y ADR-011). |
| Principio VII | Se prefiere lo nativo de Laravel; **Redis no autoriza colas**. | Sin cola ni worker: la boleta PDF se genera en la solicitud (ADR-007 no implementada). |
| Reglas de rendimiento | Índice en cada FK y columna de filtro; carga anticipada con `with()`; todo listado paginado. | Aplicadas en todas las specs. |
| Verificación manual (2.2.0) | Prueba de humo de unos 10 minutos por módulo, sin criterios cronometrados; una sola sesión de carga. | Por eso AC06, AC08 y AC09 no tienen mediciones cronometradas. |
| Idioma | Interfaz y dominio en español (Perú). | Nombres de dominio en español en `app/`. |
