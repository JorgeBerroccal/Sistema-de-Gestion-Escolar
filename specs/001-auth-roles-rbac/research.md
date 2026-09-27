# Research: Autenticación y Roles (RBAC)

**Feature**: `001-auth-roles-rbac` | **Date**: 2026-09-27

El stack está fijado por la constitución (Principio I), así que la investigación se centra en cómo
resolver cada requisito con las herramientas nativas de Laravel 13 de la forma más simple
(Principio VII).

---

## R-01. Scaffolding de autenticación: ¿Breeze/Jetstream/Fortify o propio?

- **Decision**: Login propio y mínimo: un formulario Blade clásico (`GET/POST /login`) atendido por
  un controlador con un Form Request, más `POST /logout`. Sin Breeze, Jetstream ni Fortify.
- **Rationale**: La spec excluye registro, recuperación de contraseña, verificación de correo y
  2FA. Los starter kits traen todo eso (y Breeze-Livewire además usa Volt, otro concepto más); habría
  que borrar más código del que se escribe. Un formulario HTTP clásico también simplifica la prueba
  de carga del login con k6 (no hay que reproducir el protocolo de Livewire).
- **Alternatives considered**: Breeze (stack Livewire/Volt) y quitar rutas → más código muerto y
  tests de funciones fuera de alcance. Fortify → capa adicional de configuración sin beneficio.
  Login como componente Livewire → la prueba de carga tendría que simular snapshots de Livewire.

## R-02. Login con correo o nombre de usuario, sin distinguir mayúsculas

- **Decision**: Un único campo `login`. Si contiene `@` se busca por `email`; si no, por
  `username`. Ambos se guardan normalizados en minúsculas y las columnas usan la colación
  `utf8mb4_unicode_ci` (insensible a mayúsculas) con índice único. El `username` se valida con
  `^[a-z0-9._-]{3,50}$`, por lo que nunca puede parecer un correo.
- **Rationale**: Evita ambigüedad (un texto nunca coincide con dos cuentas) sin lógica adicional.
- **Alternatives considered**: Buscar en ambas columnas con `OR` → posible ambigüedad si un username
  contuviera `@`. Dos formularios distintos → peor experiencia.

## R-03. Cuentas desactivadas (FR-003, FR-004)

- **Decision**:
  1. En el login se validan primero las credenciales; si son correctas pero `activo = false`, se
     cierra la sesión recién creada y se muestra "Tu cuenta está inactiva".
  2. Un middleware `EnsureUserIsActive` en el grupo `auth` verifica `activo` en cada solicitud; si
     es falso, hace logout, invalida la sesión y redirige al login con el mismo mensaje.
- **Rationale**: Cumple "la sesión deja de ser válida en la siguiente solicitud" sin necesidad de
  rastrear ni borrar sesiones en almacenamiento.
- **Alternatives considered**: Borrar filas de la tabla `sessions` al desactivar → acopla al driver
  de sesión y no cubre otros drivers. Incluir `activo` en `Auth::attempt` → no permite distinguir el
  mensaje de cuenta inactiva.

## R-04. Limitación de intentos (FR-006)

- **Decision**: `RateLimiter` de Laravel con clave `login|{login en minúsculas}|{ip}`: se incrementa
  solo con intentos fallidos, máximo 5 en 60 s, y se limpia con un login exitoso. Al superar el
  límite se responde con el error de validación "Demasiados intentos. Intenta en N segundos".
- **Rationale**: Es el patrón estándar de Laravel; no requiere tablas nuevas. Como solo cuenta
  fallos, no interfiere con la prueba de carga de logins válidos.
- **Alternatives considered**: `throttle` middleware por ruta → cuenta también los intentos
  exitosos y limita por IP, lo que rompería la prueba de carga desde una sola IP.

## R-05. Modelo RBAC y cómo se verifica un permiso (FR-009 a FR-015)

- **Decision**:
  - Tablas `roles`, `permisos`, `rol_permiso` y `usuarios.rol_id` (FK obligatoria, un solo rol).
  - Registro de un único `Gate::before` en `AppServiceProvider`:
    `Gate::before(fn (Usuario $u, string $ability) => $u->tienePermiso($ability) ? true : null);`
    Si el rol no tiene el permiso, se devuelve `null`; como no hay gates ni policies con ese nombre,
    Laravel deniega. Así cualquier `can('usuarios.crear')`, `@can`, `$this->authorize()` o
    middleware `can:` se resuelve contra `rol_permiso`.
  - `Usuario::tienePermiso()` carga una vez por solicitud los identificadores de permiso del rol
    (memoización en una propiedad del modelo) y compara contra ese conjunto.
  - En el código solo se referencian **identificadores de permiso**, nunca nombres de rol.
- **Rationale**: Una sola pieza de código conecta todo el sistema de autorización de Laravel con la
  tabla, cumpliendo el Principio III y FR-011 sin paquetes externos. Sin caché entre solicitudes,
  los cambios de permisos surten efecto en la siguiente solicitud (FR-015) de forma automática.
- **Alternatives considered**: `spatie/laravel-permission` → potente pero impone su propio esquema
  (`model_has_roles`, permisos directos a usuarios, guards) que no coincide con las tablas exigidas
  por la constitución y agrega conceptos innecesarios. Policies por modelo para RBAC → duplicaría la
  lógica en cada policy. Caché de permisos en Redis → complejidad e invalidación innecesarias.

## R-06. Menú según permisos (FR-013)

- **Decision**: `config/menu.php` define los ítems (etiqueta, ruta, permiso requerido). El layout
  los recorre y muestra solo los que pasan `@can($item['permiso'])`. Los ítems sin permiso (Inicio)
  siempre se muestran a usuarios autenticados.
- **Rationale**: Añadir un ítem de menú en módulos futuros es una línea de configuración; la
  visibilidad depende del permiso, no del rol.
- **Alternatives considered**: Tabla `menu` en BD → más CRUD sin requerimiento que lo pida.

## R-07. Identificadores de permisos base

- **Decision**: Formato `recurso.accion`. Permisos base del módulo:

  | Identificador | Nombre visible | Admin | Director |
  |---|---|:-:|:-:|
  | `usuarios.ver` | Ver usuarios | ✔ | ✔ |
  | `usuarios.crear` | Crear usuarios | ✔ | |
  | `usuarios.editar` | Editar usuarios, cambiar rol y restablecer contraseña | ✔ | |
  | `usuarios.desactivar` | Desactivar y reactivar usuarios | ✔ | |
  | `roles.ver` | Ver roles y permisos | ✔ | ✔ |
  | `permisos.gestionar` | Crear y editar permisos | ✔ | |
  | `roles.asignar_permisos` | Asignar permisos a roles | ✔ | |

  Docente, Padre de Familia y Alumno no reciben permisos de este módulo.
- **Rationale**: Coincide con la tabla de la spec; agrupa "crear/editar permisos" en uno porque la
  spec lo trata como una sola capacidad.
- **Alternatives considered**: `permisos.crear` y `permisos.editar` separados → granularidad que
  nadie ha pedido.

## R-08. Protección del último Administrador (FR-022)

- **Decision**: Se define "cuenta administradora" como una cuenta **activa** cuyo rol tiene a la vez
  `usuarios.editar` y `roles.asignar_permisos` (definido por permisos, no por nombre de rol). Una
  clase `ProteccionAdministracion` expone `puedeQuitarAdministracion(...)`, consultada antes de:
  desactivar un usuario, cambiar el rol de un usuario y quitar cualquiera de esos dos permisos a un
  rol. Si la operación dejaría cero cuentas administradoras, se rechaza con un error de validación.
  Además, un usuario nunca puede desactivar su propia cuenta.
- **Rationale**: Una sola consulta de conteo; cubre todas las vías de auto-bloqueo.
- **Alternatives considered**: Marcar un rol como "protegido" → sería un nombre de rol fijo de
  facto, contrario al Principio III.

## R-09. Filtros de pertenencia fail-closed (FR-025 a FR-028)

- **Decision**:
  - Interfaz `App\Autorizacion\Pertenencia\ReglaPertenencia` con dos métodos:
    `aplicar(Builder $query, Usuario $usuario): Builder` (filtra listados) y
    `permite(Usuario $usuario, Model $registro): bool` (acceso a un registro concreto).
  - Trait `RequierePertenencia` para los modelos cuyo acceso depende de la pertenencia (es la
    "declaración" exigida por FR-027). Aporta el scope `visiblePara(Usuario)` y el método
    `autorizarPertenencia(Usuario)`.
  - `config/pertenencia.php` mapea `Modelo::class => Regla::class`. Un `RegistroReglasPertenencia`
    (singleton) resuelve la regla de un modelo.
  - Si un modelo usa el trait y no tiene regla registrada, se lanza
    `ReglaPertenenciaFaltante` (subclase de `AccessDeniedHttpException`) → **403**, nunca datos sin
    filtrar. Los modelos sin el trait solo pasan por RBAC.
  - En este módulo no hay reglas reales; las pruebas usan un modelo y una regla de prueba definidos
    solo en `tests/`.
- **Rationale**: Un trait + una interfaz + un arreglo de configuración es el mínimo que da un punto
  de extensión reutilizable y seguro por defecto. Heredar de `AccessDeniedHttpException` hace que
  Laravel responda 403 sin manejador especial.
- **Alternatives considered**: Global scopes de Eloquent → se aplican implícitamente y son fáciles
  de saltar con `withoutGlobalScopes`; además no reciben al usuario de forma explícita. Policies por
  modelo → no filtran listados. Fail-open (sin regla = sin filtro) → descartado en `/speckit-clarify`.

## R-10. Administrador inicial desde el entorno (FR-024)

- **Decision**: Variables `SGE_ADMIN_USERNAME`, `SGE_ADMIN_EMAIL`, `SGE_ADMIN_PASSWORD` leídas vía
  `config/sge.php`. El seeder crea la cuenta con `firstOrCreate` si no existe y falla con un mensaje
  claro si falta `SGE_ADMIN_PASSWORD`. Nunca sobrescribe la contraseña de una cuenta ya existente.
- **Rationale**: Nada fijo en el código; la carga es repetible sin duplicados (FR-023).

## R-11. Pruebas

- **Decision**: Pest 4 (ver R-14) con `pest-plugin-laravel` y `pest-plugin-livewire`. Unit para
  `Usuario::tienePermiso`, `ProteccionAdministracion`, `RegistroReglasPertenencia`; Feature para
  login/logout, middleware de cuenta activa, rate limit, cada ruta y acción Livewire protegida
  (permitido + denegado 403), menú por rol, seeders y el mecanismo de pertenencia. Base de datos de
  pruebas MySQL 8 (`sge_testing`) en el mismo contenedor, con `RefreshDatabase`.
- **Rationale**: Probar sobre MySQL real verifica colación insensible a mayúsculas y restricciones
  de FK/únicos que SQLite no reproduce igual.
- **Alternatives considered**: SQLite en memoria → más rápido pero con comportamientos distintos en
  colación y claves foráneas.

## R-12. Prueba de carga del login (SC-006)

- **Decision**: Script k6 `tests/Load/login.js`: cada VU hace `GET /login`, extrae el token CSRF,
  hace `POST /login` con un usuario de prueba distinto y verifica la redirección a `/inicio`.
  Umbrales: `http_req_duration{name:login}` p(95) < 2000 ms, `http_req_failed` < 1 %, 100 VUs
  durante 1 minuto. Los 100 usuarios los crea `LoadTestSeeder` (solo se ejecuta explícitamente,
  nunca en `DatabaseSeeder`). k6 corre con la imagen `grafana/k6` en un perfil `load` de Compose.
- **Rationale**: Cumple el Principio V para el endpoint crítico de login sin instalar k6 localmente.

## R-13. Docker

- **Decision**: `docker-compose.yml` con `app` (imagen propia `php:8.3-fpm` + `pdo_mysql`, `bcmath`,
  `intl`, `zip`, Composer), `web` (`nginx:alpine`), `db` (`mysql:8.0`, crea `sge` y `sge_testing`
  con un script en `docker/mysql/init/`), `node` (`node:20-alpine`, perfil `dev`, para Vite) y `k6`
  (perfil `load`). Tailwind CSS vía Vite para estilos.
- **Rationale**: Configuración explícita y pequeña que sirve igual para desarrollo y despliegue.
- **Alternatives considered**: Laravel Sail → pensado solo para desarrollo y con un
  `docker-compose.yml` más grande del necesario.

## R-14. Versión del framework y de las dependencias

- **Finding (framework)**: La constitución v2.0.0 (enmienda del 2026-09-27) exige Laravel 13 sobre
  PHP 8.3+. Laravel 11 ya no recibe parches de seguridad (desde marzo de 2026) y Laravel 12 ya no
  recibe correcciones de errores (desde agosto de 2026); Laravel 13 (marzo de 2026) tiene soporte
  completo.
- **Finding (Livewire)**, verificado en `composer.json` de cada tag y en Packagist:
  - Livewire 4 declara soporte para Laravel 13 desde **v4.2.0** (2026-02-26); v4.0.0 y v4.1.0 solo
    admiten Laravel 10–12. La última versión es v4.4.6 (2026-09-21).
  - La rama 3.x también lo declara desde v3.8.0 (2026-04-30), pero es la versión mayor anterior.
  - Sin restricción explícita, `composer require livewire/livewire` instala Livewire 4.
- **Finding (Pest)**: `pest-plugin-laravel` 4.x admite `laravel/framework ^13.0` y
  `pest-plugin-livewire` 4.x admite `livewire/livewire ^4.0.1`; ambos requieren Pest 4 y PHP 8.3.
- **Decision**: Laravel 13 (`^13.0`), **Livewire 4 (`^4.2`)** y **Pest 4 (`^4.4`)** con sus
  plugins 4.x, fijados explícitamente en `composer.json`. Los componentes se escriben en formato
  **basado en clase** (clase PHP en `app/Livewire/` + vista Blade en `resources/views/livewire/`),
  que Livewire 4 sigue soportando; se configura `make_command.type = 'class'` en
  `config/livewire.php` para que `make:livewire` genere ese formato por defecto. Cambios de
  Livewire 4 que afectan a este módulo (detallados en
  [contracts/componentes-livewire.md](contracts/componentes-livewire.md#convenciones-livewire-4)):
  - rutas de página completa con `Route::livewire()`;
  - layout configurado con `component_layout`;
  - `wire:model` con `.live` explícito cuando se necesite sincronizar al escribir;
  - etiquetas `<livewire:... />` cerradas.
- **Rationale**: Fijar la versión mayor evita que el plan y los contratos describan una API
  distinta de la instalada. `^4.2` es el mínimo que admite Laravel 13. El formato por clase
  mantiene la separación PHP/Blade habitual de Laravel y es el más documentado. Los componentes de
  un solo archivo (predeterminados en v4) son una opción válida pero nueva, y no aportan nada a
  este módulo.
- **Alternatives considered**: Livewire 3.8+ → admite Laravel 13 pero es la versión mayor anterior;
  los módulos posteriores tendrían que migrar. Componentes de un solo archivo (SFC) → mezclan PHP y
  Blade en un archivo con el prefijo ⚡; menos familiar y sin beneficio aquí. Mantener Laravel 11
  → sin parches de seguridad. Laravel 12 → sin correcciones de errores durante el MVP.
