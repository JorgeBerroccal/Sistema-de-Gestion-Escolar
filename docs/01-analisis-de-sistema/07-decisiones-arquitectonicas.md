# Decisiones arquitectónicas (ADR)

> **Pregunta guía:** ¿Cómo respondimos a los drivers?

Un **ADR** (*Architecture Decision Record*, registro de decisión arquitectónica) documenta una decisión importante del diseño junto con su justificación. Cada decisión responde a uno o más drivers de [`06-driver-arquitectonicos.md`](06-driver-arquitectonicos.md).

Este documento conserva las diez decisiones del diseño inicial (ADR-001 a ADR-010) con su **estado real** y agrega tres decisiones que se tomaron durante la construcción (ADR-011 a ADR-013). Estados: **Implementada**, **Modificada** (se construyó distinto de lo decidido) y **No implementada**.

## Resumen de decisiones

| ID | Decisión arquitectónica | Driver relacionado | Estado real | Referencia |
|----|-------------------------|--------------------|-------------|------------|
| ADR-001 | Monolito modular | DA05, DA06, DA09 | **Implementada** | Constitución, Principio I; `app/` por dominio |
| ADR-002 | Patrón MVC con capa de servicios | DA05, DA07, DA09 | **Modificada** | Servicios por dominio, sin `app/Services`; sin pruebas `arch()` |
| ADR-003 | RBAC propio y configurable | DA01 | **Modificada** | Specs 001 (R-05) y 003; sin policies ni caché de permisos |
| ADR-004 | Asistente IA con contexto filtrado y adaptador | DA02 | **Modificada** | Spec 007; enmienda 2.3.0; puerto `ProveedorIA` |
| ADR-005 | Estructura académica como datos | DA04 | **Implementada** | Specs 003 y 006; pruebas `GenericidadTest` |
| ADR-006 | Caché y sesiones en Redis | DA03 | **Modificada** | Redis para sesiones y limitadores; sin caché de permisos ni estructura |
| ADR-007 | Boletas PDF en cola de trabajos | DA03 | **No implementada** | Spec 006, R-13: PDF síncrono con DomPDF |
| ADR-008 | Nginx + PHP-FPM como servidor de aplicación | DA03, DA06 | **Implementada** | Constitución 2.1.0; spec 002, D-05 |
| ADR-009 | Docker Compose en un VPS de bajo costo | DA06 | **Modificada** | De VPS genérico a Azure for Students con B2ats_v2 (diario) y B2als_v2 (carga); `docs/despliegue/costo-y-tamano.md` |
| ADR-010 | Auditoría transversal por eventos | DA08 | **No implementada** | Sin bitácora; solo `registrado_por` y `cerrado_por` |
| ADR-011 | Pertenencia fail-closed | DA01, DA02 | **Implementada** (nueva) | Spec 001, US6; specs 003 a 007 |
| ADR-012 | Sincronización de datos base | DA04, DA06 | **Implementada** (nueva) | `app/Console/Commands/SincronizarBase.php`; `deploy/deploy.sh` |
| ADR-013 | Despliegue con imágenes en GHCR y `deploy.sh` | DA06 | **Implementada** (nueva) | Spec 002, D-03 y D-04; `.github/workflows/release.yml` |

## Detalle de cada decisión

### ADR-001 — Monolito modular

- **Contexto:** un solo desarrollador, dos meses de plazo (RC10), infraestructura de bajo costo (RC09) y Laravel con Blade/Livewire como restricción (RC03, RC04).
- **Decisión:** una sola aplicación Laravel desplegable, dividida en módulos por área de negocio.
- **Alternativas descartadas:**
  - *Microservicios:* exigen varios despliegues, comunicación por red y monitoreo; no caben en el plazo ni en el servidor.
  - *Monolito sin módulos:* más rápido al inicio, pero un cambio en un área afecta a las demás (contradice AC05).
- **Consecuencias:** despliegue simple y transacciones en una sola base de datos. A cambio, todo escala junto y los límites entre módulos dependen de la disciplina del código.
- **Estado real: Implementada.** Una imagen de aplicación (`sge-app`) y los módulos como carpetas de dominio en `app/`: `Academico`, `Matricula`, `Asistencia`, `Evaluacion`, `Asistente`, `Autorizacion`. La capa web (`Http/`, `Livewire/`) sí se organiza por módulo.

### ADR-002 — Patrón MVC con capa de servicios

- **Contexto:** el sistema se construye con Laravel, Blade y Livewire (RC03, RC04); la lógica de negocio debe poder probarse (DA07) y los módulos deben cambiar sin afectar a otros (DA09).
- **Decisión:** aplicar **MVC con capa de servicios** en todos los módulos: Modelo (Eloquent), Vista (Blade), Controlador (controladores y componentes Livewire) y Servicios con las reglas de negocio. Los servicios externos se aíslan con adaptadores.
- **Alternativas descartadas:** Clean Architecture y arquitectura hexagonal en todos los módulos (capas extra sin beneficio para módulos que registran y consultan datos); MVC sin capa de servicios (reglas repartidas y difíciles de probar).
- **Consecuencias:** un mismo patrón en todo el sistema. A cambio, la regla «la lógica va en los servicios» no la impone el framework y debía verificarse con pruebas de arquitectura de Pest.
- **Estado real: Modificada.** El patrón se aplica (servicios como `EvaluacionService`, `AsistenciaService`, `MatriculaService`, `AsignacionService`, `AlumnoService`; clases puras como `CalculoPromedios`), pero: (1) los servicios viven en la carpeta de su dominio, no en `app/Services`; (2) las consultas de lectura se agrupan en clases como `ConsultaNotas` y `ResumenAsistencia`; (3) **no hay pruebas `arch()`**: se verificó con una búsqueda en `tests/` sin resultados. Las reglas se comprueban con pruebas específicas (`SinCondicionalesDeRolTest`, `GenericidadTest`).

### ADR-003 — RBAC propio y configurable

- **Contexto:** cinco roles con accesos distintos, datos de menores de edad (RC15) y la restricción de usar un modelo de roles propio (RC06).
- **Decisión:** tablas Rol, Permiso y Rol_Permiso; la verificación se hace en middleware y policies antes de llegar a cualquier controlador.
- **Alternativas descartadas:** roles fijos en el código (`if rol == docente`).
- **Consecuencias:** se pueden ajustar permisos desde la administración. A cambio, cada solicitud consulta permisos, por lo que se planeó guardarlos en caché (ADR-006).
- **Estado real: Modificada.** Se implementó el modelo de datos y la verificación por identificador de permiso, con tres diferencias respecto a la decisión:
  - Las tablas son `roles`, `permisos` y `rol_permiso` (migraciones `2026_09_27_000001` a `000003`) y la autorización se resuelve con `Gate::before` (`AppServiceProvider`) y middleware `can:` en `routes/web.php`; **no hay clases Policy** (`app/Policies` no existe).
  - Las pantallas que dependen de datos propios comprueban el permiso en `mount()` y aplican la pertenencia (ADR-011).
  - **No hay caché de permisos**: `Usuario::tienePermiso()` lee `rol->permisos` una vez por objeto de usuario y solicitud.
- Crear roles desde la interfaz no está implementado (spec 001, supuestos); los cinco roles vienen de `RolesYPermisosSeeder`.
- **Cómo se verificó:** lectura de `routes/web.php`, `AppServiceProvider`, `Usuario::tienePermiso()`, `ls app/Policies` (no existe) y búsqueda de `Cache::`/`remember` en `app/` (sin resultados).

### ADR-004 — Asistente IA con contexto filtrado y adaptador

- **Contexto:** el asistente no debe revelar datos de otros estudiantes (AC02), usa un servicio externo (RC07) y debe poder simularse en la prueba de carga (RC18).
- **Decisión original:** `AsistenteService` pide a Asistencia y Evaluación solo los datos permitidos, arma el contexto y llama a un contrato `ServicioAsistente`; la API de Claude se usa mediante un adaptador.
- **Alternativas descartadas:** que la IA consulte la base de datos por su cuenta; llamar a la API de Claude directamente desde el servicio.
- **Estado real: Modificada.** La idea (contexto filtrado en el servidor y proveedor detrás de una interfaz) se mantuvo; cambió el proveedor y los nombres:
  - Puerto `App\Asistente\Proveedores\ProveedorIA` (`responder()`, `admiteDatosReales()`, `nombre()`), con `ProveedorFalso` (pruebas y desarrollo, nunca sale a la red) y `ProveedorOpenRouter` (modelo y respaldos por entorno). `ProveedorNoDisponible` modela la falla del proveedor. Se elige con `ASISTENTE_PROVEEDOR` en `AppServiceProvider`.
  - **Enmienda 2.3.0 de la constitución:** el Principio VI pasó de fijar la API de Claude a exigir un puerto con adaptadores intercambiables, porque no hay presupuesto para créditos de Anthropic. El adaptador de Gemini está documentado como plan B y **no implementado**.
  - **Falla cerrado:** el adaptador de OpenRouter con un modelo `:free` declara que no admite datos reales y, en `APP_ENV=production`, el asistente se niega a funcionar (`Disponibilidad`).
  - `ConstructorContexto` arma el contexto con las mismas consultas de las pantallas (`ConsultaNotas`, `ResumenAsistencia`) para **un solo alumno** elegido por `ResolverAlumno` entre los propios; no incluye documento, fecha de nacimiento ni correo. `LimiteSolicitudes` aplica cuatro contadores (por usuario y globales, por minuto y por día).
  - No hay clases `AsistenteClaude`, `AsistenteSimulado` ni `ServicioAsistente`.
  - Fuentes: spec 007 (research R-01 a R-13), `docs/asistente.md`, `app/Asistente/`.

### ADR-005 — Estructura académica como datos

- **Contexto:** el sistema debe servir a colegios con distinta cantidad de grados, secciones, periodos y escala de calificación (AC06, RC14).
- **Decisión:** modelar la estructura académica con entidades genéricas que cada institución configura.
- **Alternativas descartadas:** estructura fija en el código.
- **Consecuencias:** Matrícula, Asistencia y Evaluación dependen de Gestión Académica.
- **Estado real: Implementada.** Modelos `Institucion`, `Nivel`, `Grado`, `Seccion`, `Curso`, `AnioEscolar`, `PeriodoEvaluacion` y `EscalaCalificacion`, y también catálogos como datos: `TipoDocumento`, `EstadoAsistencia` y los criterios de evaluación. La escala guarda `equivalentes` y `umbral_aprobacion` (spec 006, R-03). Pruebas `GenericidadTest` en los módulos 2 a 5.
- **Cómo se verificó:** lectura de `app/Models/`, de las migraciones `2026_10_24_*` y de las pruebas `GenericidadTest.php`.

### ADR-006 — Caché y sesiones en Redis

- **Contexto:** meta de 1000 usuarios simultáneos con p95 < 2 s (AC03).
- **Decisión:** guardar en Redis las sesiones, los permisos por rol y la estructura académica.
- **Alternativas descartadas:** sesiones en archivos o en MySQL.
- **Estado real: Modificada.** Redis 7 (`phpredis`) guarda las **sesiones** (`SESSION_DRIVER=redis`), el **limitador de intentos del login** y los **limitadores del asistente**; `CACHE_STORE=redis` está configurado, pero el código de la aplicación **no usa la caché**: no hay llamadas a `Cache::` ni a `remember()` en `app/`. Los permisos y la estructura académica se leen de MySQL en cada solicitud. Constitución, Principio I (2.1.0) y spec 001, R-15.
- **Cómo se verificó:** búsqueda de `Cache::`, `cache(` y `remember` en `app/`; lectura de `docker-compose.prod.yml` y `.env.example`.

### ADR-007 — Boletas PDF en cola de trabajos

- **Contexto:** generar un PDF consume CPU y memoria y al cierre de bimestre se piden muchos a la vez.
- **Decisión original:** Evaluación encola la generación; un worker la procesa y guarda el archivo.
- **Estado real: No implementada.** La boleta se genera **en la misma solicitud** con `barryvdh/laravel-dompdf` desde `BoletaController::descargar` y `BoletaService`; no se guarda en disco ni hay cola ni worker (`QUEUE_CONNECTION=sync`). Motivos (spec 006, research R-13 y plan): la constitución no autoriza colas (Principio VII); una página A4 se genera muy por debajo de 5 s; sin almacenamiento no hay copias obsoletas ni datos de menores en disco. Consecuencia medida: en la sesión de carga la boleta fue la operación más lenta (mediana 8,91 s; p95 16,40 s).

### ADR-008 — Nginx + PHP-FPM como servidor de aplicación

- **Contexto:** la propuesta inicial se basaba en Laravel Sail, que es un entorno de desarrollo (RC08).
- **Decisión:** en producción, nginx termina HTTPS y entrega a PHP-FPM con OPcache; Octane como opción.
- **Estado real: Implementada.** Contenedor `web` (nginx, `docker/nginx/prod.conf`) y `app` (`php:8.3-fpm`); OPcache con `OPCACHE_VALIDATE_TIMESTAMPS=0` y `php artisan optimize` en cada arranque. **Octane quedó descartado** en la constitución 2.1.0. El certificado de Let's Encrypt lo gestiona certbot en el servidor y nginx lo lee (spec 002, D-05).

### ADR-009 — Docker Compose en un servidor de bajo costo

- **Contexto:** infraestructura de bajo costo (RC09) y entorno equivalente entre desarrollo y producción (AC09).
- **Decisión original:** cinco contenedores (Nginx, aplicación, worker, MySQL y Redis) en un VPS de 2 vCPU / 4 GB, levantados con `docker compose up -d`.
- **Alternativas descartadas:** AWS u otra nube pública; PaaS (Railway, Render).
- **Estado real: Modificada.** Dos cambios:
  - **Cuatro contenedores**, no cinco: `web`, `app`, `db` y `redis` (no hay worker, ADR-007).
  - **Azure for Students en lugar de un VPS genérico.** Una VM Ubuntu en la región Chile Central con dos tamaños: `Standard_B2ats_v2` (2 vCPU, 1 GB; 9,64 USD/mes) en el **perfil diario** y `Standard_B2als_v2` (2 vCPU, 4 GB; 38,40 USD/mes) solo en el **perfil carga**, para las sesiones de k6 y la espera previa de créditos de CPU. `PERFIL_TAMANO` ajusta FPM, MySQL y Redis a cada tamaño, y `deploy.sh` advierte si el tamaño de la VM no coincide con el perfil. Crédito de 100 USD con tope de planificación del 80 %.
  - Referencias: `docs/despliegue/costo-y-tamano.md`, `docs/despliegue/aprovisionamiento-azure.md` y spec 002 (D-01, D-01c, D-02).

### ADR-010 — Auditoría transversal por eventos

- **Contexto:** deben quedar registrados los inicios de sesión, los cambios de notas y la gestión de usuarios (RF22, AC10).
- **Decisión original:** los servicios emiten eventos y un componente de Auditoría escribe la bitácora.
- **Estado real: No implementada.** No hay eventos, listeners ni tabla de bitácora. La trazabilidad se limita a `registrado_por` en `notas` y `asistencias` (quién escribió por última vez) y a `cerrado_por` y `cerrado_en` en `cierres_periodo` (spec 006, R-05: «fila = auditoría de quién y cuándo»). No se conserva el valor anterior ni hay registro de inicios de sesión ni de gestión de usuarios.
- **Cómo se verificó:** búsqueda de `audit`, `bitacora`, `registrado_por` y `cerrado_por` en `app/` y `database/migrations/`; no existen `app/Events` ni `app/Listeners`.

### ADR-011 — Pertenencia fail-closed (nueva)

- **Contexto:** el permiso por rol no basta: un padre solo debe ver a sus hijos y un docente solo sus secciones (constitución, Principio III, última viñeta; AC01). Un filtro olvidado en una pantalla expone datos de menores.
- **Decisión:** un mecanismo reutilizable y **fail-closed** (spec 001, US6 y FR-025 a FR-028):
  - El trait `RequierePertenencia` añade al modelo el scope `visiblePara($usuario)` y `autorizarPertenencia()`.
  - Cada modelo se asocia con su regla (interfaz `ReglaPertenencia`) en `config/pertenencia.php`; `RegistroReglasPertenencia` la resuelve.
  - Un modelo con el trait y **sin regla registrada se deniega** (`ReglaPertenenciaFaltante`, 403).
  - Reglas implementadas: `AlumnoRegla`, `AsignacionDocenteRegla`, `MatriculaRegla`, `AsistenciaRegla`, `CriterioRegla` y `NotaRegla`. Una regla decide por permiso (`*.ver_todas`, `*.ver_propia`, `*.registrar_propia`) y por la relación del usuario con los datos (asignación docente, vínculo de apoderado o de alumno); nunca por nombre de rol. Si el usuario no tiene ningún permiso aplicable, la consulta devuelve vacío.
  - Las notas visibles para la familia incluyen además la condición de periodo cerrado (publicado), aplicada en la consulta y no en cada pantalla (spec 006, R-07).
- **Alternativas descartadas:** filtrar en cada componente (se olvida, spec 005, R-07); comparar nombres de rol (prohibido por el Principio III).
- **Consecuencias:** una sola puerta para los datos propios, reutilizada por Matrícula, Asistencia, Evaluación y el asistente (`MatriculaRegla::alumnosPropios`). A cambio, cada modelo nuevo con datos de alumnos debe declarar su regla.
- **Fuentes:** `app/Autorizacion/Pertenencia/`, `config/pertenencia.php`, `tests/Feature/Pertenencia/` y `tests/Unit/Autorizacion/RegistroReglasPertenenciaTest.php`.

### ADR-012 — Sincronización de datos base (nueva)

- **Contexto:** los permisos, roles, escalas, tipos de documento y estados de asistencia son datos que el código necesita en todo entorno, y cada módulo agrega los suyos. En staging no puede haber pasos manuales (specs 003, 004, 005 y 006: «los permisos nuevos deben llegar al servidor en el despliegue»).
- **Decisión:** el comando `php artisan sge:sincronizar-base` ejecuta una lista fija de seeders idempotentes (`RolesYPermisosSeeder`, `EscalasCalificacionSeeder`, `TiposDocumentoSeeder`, `EstadosAsistenciaSeeder`). `deploy/deploy.sh` lo corre después de `migrate`. Solo crea lo que falta y **nunca crea usuarios**: el administrador inicial y los datos de ejemplo van en seeders separados, y los de ejemplo rechazan producción. Un permiso solo se asigna a sus roles la primera vez que se crea, para no deshacer cambios del Administrador.
- **Alternativas descartadas:** `migrate:fresh --seed` en cada despliegue (borra datos); crear los permisos a mano en el servidor (se olvida y genera diferencias entre entornos).
- **Consecuencias:** los entornos convergen sin intervención. A cambio, los permisos eliminados o renombrados en una versión futura hay que limpiarlos a mano, y en staging el conteo de permisos puede diferir del seeder si se creó alguno a mano.
- **Fuentes:** `app/Console/Commands/SincronizarBase.php`, `database/seeders/`, `deploy/deploy.sh`, `tests/Feature/Console/SincronizarBaseTest.php`, README («Datos base y de ejemplo»).

### ADR-013 — Despliegue con imágenes en GHCR y `deploy.sh` (nueva)

- **Contexto:** el servidor de uso diario tiene 1 GB de RAM y no puede ejecutar `composer` ni `npm` (spec 002, D-02 y D-03).
- **Decisión:** el workflow `release.yml` construye las imágenes en GitHub Actions al subir una etiqueta `v*` y publica `ghcr.io/jorgeberroccal/sge-app:<etiqueta>` y `sge-web:<etiqueta>`. En el servidor, `deploy/deploy.sh <etiqueta>` descarga esas imágenes, respalda la base, migra, sincroniza datos base (ADR-012), recrea `app` y `web` y verifica la versión servida por `/up`. **El servidor nunca construye.** Volver atrás es desplegar la etiqueta anterior. El CI (`ci.yml`) ejecuta Pest y Pint en cada push y pull request.
- **Alternativas descartadas:** construir en el servidor (agota la RAM); copiar un tar de la imagen por SSH (sin historial ni caché); Docker Hub (límites de descarga y cuenta extra); registro propio (complejidad).
- **Consecuencias:** la misma imagen que se probó es la que corre y la versión es la etiqueta. A cambio, se depende de GitHub Actions y GHCR, y los paquetes deben quedar públicos tras la primera publicación para que el servidor no guarde credenciales.
- **Evidencia:** despliegue de `v0.2.0` en 27 s y vuelta atrás en 22 s; recuperación de una migración fallida en 4 min 7 s (`specs/002-despliegue-ci-staging/evidencias/`).
- **Fuentes:** `.github/workflows/release.yml`, `deploy/deploy.sh`, `docker/php/Dockerfile.prod`, `docs/despliegue/desplegar-y-volver-atras.md`.

## Trazabilidad driver → decisión

| Driver | Decisiones |
|--------|------------|
| DA01 RBAC configurable | ADR-003, ADR-011 |
| DA02 IA con privacidad | ADR-004, ADR-011 |
| DA03 Rendimiento con 1000 usuarios | ADR-006, ADR-007 (no implementada), ADR-008 |
| DA04 Estructura configurable | ADR-005, ADR-012 |
| DA05 Laravel + Blade/Livewire | ADR-001, ADR-002 |
| DA06 Docker de bajo costo | ADR-001, ADR-008, ADR-009, ADR-012, ADR-013 |
| DA07 Testeabilidad | ADR-002 |
| DA08 Auditoría | ADR-010 (no implementada) |
| DA09 Mantenibilidad | ADR-001, ADR-002 |
