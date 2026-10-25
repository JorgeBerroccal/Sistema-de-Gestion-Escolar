---

description: "Task list for 001-auth-roles-rbac (Autenticación y Roles)"
---

# Tasks: Autenticación y Roles (RBAC)

**Input**: Design documents from `/specs/001-auth-roles-rbac/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: OBLIGATORIAS. Criterio de clasificación: toda prueba que usa la base de datos es
**Feature** (integración) y vive en `tests/Feature/`; `tests/Unit/` solo contiene pruebas sin acceso
a datos. La constitución (Principio V) exige pruebas Unit + Feature para toda
tarea, un test de acceso permitido y otro denegado (403) por cada regla de autorización, y una
prueba de carga k6 (login y consultas, hasta 1000 VUs). En cada historia las pruebas se escriben primero y deben fallar
antes de implementar.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1…US6, numeración de spec.md)
- Include exact file paths in descriptions

## Path Conventions

Monolito Laravel 13 en la raíz del repositorio (`app/`, `config/`, `database/`, `resources/`,
`routes/`, `tests/`, `docker/`), según plan.md. Referencias frecuentes:

- Rutas y respuestas: [contracts/rutas-web.md](contracts/rutas-web.md)
- Acciones Livewire y validaciones: [contracts/componentes-livewire.md](contracts/componentes-livewire.md)
- Pertenencia: [contracts/pertenencia.md](contracts/pertenencia.md)
- Tablas y reglas: [data-model.md](data-model.md); decisiones: [research.md](research.md) (R-01…R-14)

Reglas globales para TODAS las tareas:

- Nunca comparar nombres de rol en `app/`, `routes/`, `config/` ni `resources/` (Principio III):
  solo identificadores de permiso vía `can()` / `@can` / `authorize()` / middleware `can:`.
- Todo comando PHP/Composer/Artisan se ejecuta dentro del contenedor:
  `docker compose exec app <comando>`.
- Textos de interfaz y mensajes en español.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Crear el proyecto Laravel 13, el entorno Docker y las dependencias fijadas en R-14.

- [x] T001 Crear el proyecto Laravel 13 en la raíz del repositorio: ejecutar `composer create-project laravel/laravel:^13.0 _tmp_app` (con la imagen `composer:2` si aún no existe el contenedor `app`), mover su contenido a la raíz sin sobrescribir `.specify/`, `specs/`, `.claude/` ni `.gitignore`, y borrar `_tmp_app/`. Fusionar en el `.gitignore` existente las entradas del `.gitignore` de Laravel que falten (conservar todas las actuales, incluida `!.env.example`). No agregar reglas propias para `storage/` ni `bootstrap/cache/`: el esqueleto de Laravel ya trae un `.gitignore` anidado en cada subcarpeta (`storage/app/.gitignore`, `storage/framework/*/.gitignore`, `storage/logs/.gitignore`, `bootstrap/cache/.gitignore`, cada uno con el patrón `*` + `!.gitignore`) que versiona la estructura de directorios e ignora su contenido de runtime; verificar con `git add -n storage bootstrap/cache` que solo se añadirían esos archivos `.gitignore` y ningún archivo de runtime. No crear archivos `.gitkeep`: quedarían ignorados por esos mismos `.gitignore` anidados y no aportarían nada. Descartar `CLAUDE.md` y `AGENTS.md` del esqueleto (boilerplate de Laravel Boost para instalar PHP/Composer/MCP locales; no aplica a este flujo basado en Docker) y `database/database.sqlite` (el proyecto usa MySQL, no SQLite). **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T002 Crear `docker/php/Dockerfile` basado en `php:8.3-fpm` con extensiones `pdo_mysql`, `bcmath`, `intl`, `zip`, `pcntl`, Composer 2 copiado desde `composer:2`, `WORKDIR /var/www/html` y usuario no root con UID/GID configurables por `ARG`. **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T003 [P] Crear `docker/nginx/default.conf` sirviendo `/var/www/html/public`, `try_files $uri $uri/ /index.php?$query_string;` y `fastcgi_pass app:9000`. **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T004 [P] Crear `docker/mysql/init/01-create-testing-db.sql` que cree la base `sge_testing` (`utf8mb4`, `utf8mb4_unicode_ci`) y otorgue todos sus privilegios al usuario `${DB_USERNAME}` definido en `.env` (usar el nombre de usuario literal `sge`, igual que en `.env.example`). **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T005 Crear `docker-compose.yml` con servicios: `app` (build `docker/php/Dockerfile`, monta `.:/var/www/html`), `web` (`nginx:alpine`, puerto `8080:80`, monta `docker/nginx/default.conf`), `db` (`mysql:8.0`, `MYSQL_DATABASE=sge`, `MYSQL_USER=sge`, volumen nombrado `db_data`, monta `docker/mysql/init/` en `/docker-entrypoint-initdb.d`, `--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`, healthcheck), `node` (`node:20-alpine`, `profiles: [dev]`, monta `.:/app`, `working_dir: /app`) y `k6` (`grafana/k6`, `profiles: [load]`, monta `tests/Load:/scripts`, red compartida para llegar a `http://web`). **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T006 Instalar dependencias fijadas (research R-14): `composer require livewire/livewire:^4.2` y `composer require --dev pestphp/pest:^4.4 pestphp/pest-plugin-laravel:^4.0 pestphp/pest-plugin-livewire:^4.0 --with-all-dependencies`; ejecutar `./vendor/bin/pest --init`; borrar `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`. **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T007 [P] Publicar `config/livewire.php` (`php artisan livewire:publish --config`) y fijar `'make_command' => ['type' => 'class']` y `'component_layout' => 'layouts::app'` (contracts/componentes-livewire.md, "Convenciones Livewire 4"). **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T008 [P] Verificar que el esqueleto trae Tailwind CSS vía Vite (`vite.config.js`, `resources/css/app.css`, `package.json`); si no, instalar `tailwindcss` y `@tailwindcss/vite` y configurarlos. `resources/css/app.css` debe incluir las fuentes de `resources/views/**/*.blade.php` y `app/Livewire/**/*.php`. **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T009 Actualizar `.env.example` con: `APP_NAME=SGE`, `APP_LOCALE=es`, `APP_URL=http://localhost:8080`, `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=sge`, `DB_USERNAME=sge`, `DB_PASSWORD=secret`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `REDIS_CLIENT=phpredis`, `REDIS_HOST=redis`, `REDIS_PORT=6379`, `SESSION_LIFETIME=120`, `SGE_ADMIN_USERNAME=admin`, `SGE_ADMIN_EMAIL=admin@example.com`, `SGE_ADMIN_PASSWORD=` (vacío, con comentario que indique que es obligatorio). Copiar a `.env` local. **Cubre**: SC-008.
- [x] T010 [P] Configurar `phpunit.xml` para pruebas sobre MySQL: `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_DATABASE=sge_testing`, `SESSION_DRIVER=array`, `CACHE_STORE=array`, `BCRYPT_ROUNDS=4`, `SGE_ADMIN_PASSWORD=password-de-prueba` (research R-11). **Cubre**: SC-001, SC-002.
- [x] T011 [P] Crear `config/sge.php` que devuelva `['admin' => ['username' => env('SGE_ADMIN_USERNAME'), 'email' => env('SGE_ADMIN_EMAIL'), 'password' => env('SGE_ADMIN_PASSWORD')]]` (research R-10). **Cubre**: FR-024.
- [x] T012 [P] Crear `lang/es/validation.php` con mensajes en español para las reglas usadas en el módulo: `required`, `string`, `max.string`, `min.string`, `email`, `unique`, `exists`, `regex`, `confirmed`, `array`, y un bloque `attributes` que traduzca `login`, `password`, `nombres`, `apellidos`, `username`, `email`, `rol_id`, `identificador`, `nombre`, `descripcion`. **Cubre**: FR-002, FR-007, FR-021.
- [x] T099 Ampliar `docker/php/Dockerfile` (T002) y agregar configuración PHP (constitución v2.1.0, research R-15): instalar la extensión `redis` (phpredis, `pecl install redis` + `docker-php-ext-enable redis`) y `opcache` (`docker-php-ext-install opcache`); crear `docker/php/opcache.ini` (`opcache.enable=1`, `opcache.memory_consumption=192`, `opcache.max_accelerated_files=20000`, `opcache.validate_timestamps=${OPCACHE_VALIDATE_TIMESTAMPS:-1}`) y `docker/php/fpm-pool.conf` (`pm = dynamic`, `pm.max_children = 20`, `pm.start_servers = 6`, `pm.min_spare_servers = 4`, `pm.max_spare_servers = 10`, `pm.max_requests = 500`), copiándolos a `$PHP_INI_DIR/conf.d/` y `/usr/local/etc/php-fpm.d/zz-sge.conf`. Agregar `OPCACHE_VALIDATE_TIMESTAMPS=1` a `.env.example` (comentario: `0` en producción). **Cubre**: SC-006.
- [x] T100 Agregar a `docker-compose.yml` (T005) el servicio `redis` (`redis:7-alpine`, `command: redis-server --maxmemory 256mb --maxmemory-policy volatile-lru --save ""`, healthcheck `redis-cli ping`) y hacer que `app` dependa de él con `condition: service_healthy`; pasar `OPCACHE_VALIDATE_TIMESTAMPS` al servicio `app` (depende de T005, T099). **Cubre**: SC-006.

**Checkpoint**: `docker compose up -d --build` levanta `app`, `web`, `db` y `redis`; `http://localhost:8080` muestra la página por defecto de Laravel y `docker compose exec app ./vendor/bin/pest` ejecuta 0 pruebas sin errores.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema de datos, modelos, factories, layout base, helpers de prueba y la regla de
"cuenta administradora", que usan todas las historias.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T013 Eliminar las tres migraciones del esqueleto de Laravel (`0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`): `users` y `password_reset_tokens` se sustituyen por `usuarios`, y `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` y `failed_jobs` no se usan porque sesiones y caché van a Redis y no hay colas (data-model.md "Tablas estándar"). Ajustar `config/session.php`, `config/cache.php` y `config/queue.php` (y `QUEUE_CONNECTION=sync` en `.env.example`) para que no apunten a esas tablas. **Cubre**: — (infraestructura, sin FR/SC propios).
- [x] T014 [P] Crear `database/migrations/2026_09_27_000001_create_roles_table.php`: tabla `roles` con `id`, `nombre` varchar(50) "obligatorio, único", `descripcion` varchar(255) "opcional", timestamps. **Cubre**: FR-010.
- [x] T015 [P] Crear `database/migrations/2026_09_27_000002_create_permisos_table.php`: tabla `permisos` con `id`, `identificador` varchar(100) "obligatorio, único, inmutable", `nombre` varchar(100) "obligatorio", `descripcion` varchar(255) "opcional", timestamps. **Cubre**: FR-010, FR-014.
- [x] T016 Crear `database/migrations/2026_09_27_000003_create_rol_permiso_table.php`: tabla `rol_permiso` con `rol_id` FK → `roles.id` `ON DELETE CASCADE`, `permiso_id` FK → `permisos.id` `ON DELETE CASCADE`, PK compuesta (`rol_id`, `permiso_id`), timestamps (depende de T014, T015). **Cubre**: FR-010.
- [x] T017 Crear `database/migrations/2026_09_27_000004_create_usuarios_table.php`: tabla `usuarios` con `id`, `nombres` varchar(100) obligatorio, `apellidos` varchar(100) obligatorio, `username` varchar(50) único, `email` varchar(150) nullable único, `password` varchar(255), `rol_id` FK → `roles.id` `ON DELETE RESTRICT` obligatorio, `activo` boolean default `true`, `rememberToken()`, timestamps (depende de T014). Índices (constitución v2.1.0, reglas de rendimiento): además de los únicos y la FK `rol_id`, `->index()` en `activo`, `nombres` y `apellidos` (búsqueda y filtrado del listado). **Cubre**: FR-009, FR-021.
- [x] T018 [P] Crear `app/Models/Rol.php` con `protected $table = 'roles'`, `$fillable = ['nombre', 'descripcion']`, relaciones `usuarios()` hasMany `Usuario` (FK `rol_id`) y `permisos()` belongsToMany `Permiso` tabla `rol_permiso` (`rol_id`, `permiso_id`) `->withTimestamps()`. **Cubre**: FR-010.
- [x] T019 [P] Crear `app/Models/Permiso.php` con `$fillable = ['identificador', 'nombre', 'descripcion']`, relación `roles()` belongsToMany `Rol` tabla `rol_permiso`, constante pública `REGEX_IDENTIFICADOR = '/^[a-z_]+(\.[a-z_]+)+$/'`, y un evento `updating` que lance `LogicException('El identificador de un permiso es inmutable.')` si `identificador` cambió (data-model.md: "Solo se fija al crear"). **Cubre**: FR-014.
- [x] T020 Crear `app/Models/Usuario.php` (extiende `Illuminate\Foundation\Auth\User`) con `$fillable = ['nombres', 'apellidos', 'username', 'email', 'password', 'rol_id', 'activo']`, `$hidden = ['password', 'remember_token']`, casts `password => 'hashed'` y `activo => 'boolean'`, mutators que guarden `username` y `email` en minúsculas (`email` vacío → `null`), relación `rol()` belongsTo `Rol`, accesor `nombre_completo` y constante pública `REGEX_USERNAME = '/^[a-z0-9._-]{3,50}$/'`. Borrar `app/Models/User.php` (depende de T018). **Cubre**: FR-007, FR-009, FR-021.
- [x] T021 Actualizar `config/auth.php`: provider `users` → `'model' => App\Models\Usuario::class`; eliminar la sección `passwords` o dejarla sin uso documentado (no hay recuperación de contraseña). **Cubre**: FR-001.
- [x] T022 [P] Crear `database/factories/RolFactory.php` (nombre único y corto: `'Rol '.fake()->unique()->lexify('????????')`; `jobTitle()` puede superar los 50 caracteres de `roles.nombre`), `database/factories/PermisoFactory.php` (`identificador` con formato `recurso.accion` único, p. ej. `'prueba.' . fake()->unique()->lexify('??????')`) y `database/factories/UsuarioFactory.php` (username único `^[a-z0-9._-]{3,50}$`, email único, password `'password'`, `rol_id` → `Rol::factory()`, `activo => true`, estado `inactivo()`). Borrar `database/factories/UserFactory.php`. **Cubre**: — (soporte de pruebas).
- [x] T023 [P] Crear `resources/views/layouts/app.blade.php` (layout autenticado: `@vite`, `@livewireStyles`/`@livewireScripts` si hace falta, cabecera con nombre del usuario y botón "Cerrar sesión" que envía `POST /logout` con `@csrf`, contenedor `{{ $slot ?? '' }}` / `@yield('content')`, y un `@include('layouts.partials.menu')` que en esta fase es un archivo vacío `resources/views/layouts/partials/menu.blade.php`) y `resources/views/layouts/guest.blade.php` (layout sin menú para el login). **Cubre**: FR-005, FR-013.
- [x] T024 Configurar `tests/Pest.php`: `pest()->extend(Tests\TestCase::class)->use(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature')` (toda prueba que toca la base de datos es Feature) y `pest()->extend(Tests\TestCase::class)->in('Unit')` **sin** `RefreshDatabase` (las Unit pueden usar el contenedor y `config()`, pero nunca la BD); helper global `usuarioConPermisos(array $identificadores = [], array $atributos = []): App\Models\Usuario` que cree un rol de fábrica, cree/obtenga los permisos por identificador, los asigne al rol y devuelva un usuario activo de ese rol (depende de T018–T022). **Cubre**: — (soporte de pruebas).
- [x] T025 [P] Escribir `tests/Feature/Models/UsuarioTest.php`: `username` y `email` se guardan en minúsculas; email vacío se guarda como `null`; la contraseña se guarda con hash y `Hash::check` funciona; un usuario sin `rol_id` no se puede insertar (excepción de BD). **Cubre**: FR-007, FR-009, FR-021.
- [x] T026 [P] Escribir `tests/Feature/Models/PermisoTest.php`: crear un permiso funciona; cambiar `nombre` o `descripcion` funciona; cambiar `identificador` lanza `LogicException`; un par rol–permiso duplicado en `rol_permiso` lanza excepción de BD. **Cubre**: FR-014.
- [x] T027 Escribir `tests/Feature/Autorizacion/ProteccionAdministracionTest.php`: cubre las tres situaciones de la tabla "Regla derivada: cuenta administradora" de data-model.md — desactivar a la única cuenta administradora → rechazado; cambiar su rol a uno sin los permisos → rechazado; quitar `usuarios.editar` o `roles.asignar_permisos` al rol de la única cuenta administradora → rechazado; con dos cuentas administradoras activas las tres operaciones se permiten; una cuenta inactiva no cuenta como administradora. **Cubre**: FR-022.
- [x] T028 Implementar `app/Autorizacion/ProteccionAdministracion.php` (research R-08): constante `PERMISOS_ADMINISTRACION = ['usuarios.editar', 'roles.asignar_permisos']`; método `contarCuentasAdministradoras(?int $excluirUsuarioId = null, ?array $simularPermisosRol = null): int` que cuente usuarios `activo = true` cuyo rol tenga ambos permisos (con `whereHas` sobre `rol.permisos`); y métodos públicos `puedeDesactivar(Usuario $objetivo): bool`, `puedeCambiarRol(Usuario $objetivo, Rol $nuevoRol): bool`, `puedeSincronizarPermisos(Rol $rol, array $permisoIds): bool`. Sin referencias a nombres de rol. Hacer pasar T027. **Cubre**: FR-022.

**Checkpoint**: `php artisan migrate:fresh` crea solo `roles`, `permisos`, `rol_permiso` y `usuarios` (más `migrations`); T025–T027 en verde.

---

## Phase 3: User Story 1 - Iniciar y cerrar sesión (Priority: P1) 🎯 MVP

**Goal**: Cualquier usuario activo entra con correo o nombre de usuario + contraseña y puede salir;
credenciales inválidas, cuentas inactivas y ráfagas de intentos se rechazan (FR-001…FR-008).

**Independent Test**: Con usuarios creados por factory: login con correo, con username, con
contraseña incorrecta, con usuario inexistente, con cuenta inactiva; logout; acceso sin sesión a
`/inicio` redirige a `/login`.

### Tests for User Story 1 ⚠️

> Escribir primero; deben FALLAR antes de implementar.

- [x] T029 [P] [US1] Escribir `tests/Feature/Auth/LoginTest.php` según la tabla "Resultados de `POST /login`" de contracts/rutas-web.md: `GET /login` responde 200; login con email → 302 a `/inicio` y autenticado; login con username → igual; login con `USERNAME` en mayúsculas → funciona; contraseña incorrecta → 302 a `/login`, error en `login` exactamente "Las credenciales no son válidas.", no autenticado; usuario inexistente → mismo mensaje; campos vacíos → errores de validación en `login` y `password`; la sesión se regenera tras login exitoso; usuario autenticado que visita `/login` → 302 a `/inicio`. **Cubre**: FR-001, FR-002, FR-003, FR-005, SC-001.
- [x] T030 [P] [US1] Escribir `tests/Feature/Auth/CuentaInactivaTest.php`: login con credenciales válidas de una cuenta `activo=false` → 302 a `/login`, error "Tu cuenta está inactiva. Contacta al administrador.", no autenticado; un usuario autenticado cuya cuenta se marca `activo=false` en BD es deslogueado en su siguiente `GET /inicio`, redirigido a `/login` con el mismo mensaje y la sesión queda invalidada. **Cubre**: FR-003, FR-004, SC-001.
- [x] T031 [P] [US1] Escribir `tests/Feature/Auth/LimiteIntentosTest.php`: 5 intentos fallidos con el mismo `login` desde la misma IP → el 6.º responde con error que empieza por "Demasiados intentos." aunque la contraseña sea correcta; tras `travel(61)->seconds()` se puede volver a iniciar sesión; un login exitoso limpia el contador; los intentos con otro `login` no se ven afectados. **Cubre**: FR-006.
- [x] T032 [P] [US1] Escribir `tests/Feature/Auth/LogoutTest.php`: `POST /logout` autenticado → 302 a `/login`, sesión invalidada; después `GET /inicio` → 302 a `/login`; `GET /inicio` sin sesión → 302 a `/login`; `GET /` autenticado → 302 a `/inicio`; `/inicio` responde 200 a un usuario sin ningún permiso (FR edge case "Rol sin permisos"). **Cubre**: FR-005.
- [x] T033 [P] [US1] Escribir `tests/Unit/Auth/LoginRequestTest.php` (sin BD): `LoginRequest::columnaPara('ana@colegio.pe')` → `'email'`; `columnaPara('ana.perez')` → `'username'`; `LoginRequest::claveLimite('  Ana.Perez ', '10.0.0.1')` → `'login|ana.perez|10.0.0.1'`; la misma entrada en mayúsculas y minúsculas produce la misma clave; IPs distintas producen claves distintas. **Cubre**: FR-001, FR-006.
- [x] T034 [P] [US1] Escribir `tests/Unit/Config/SesionTest.php` (sin BD, FR-008): `config('session.lifetime')` es exactamente `120` y `config('session.expire_on_close')` es `false`. **Cubre**: FR-008.

### Implementation for User Story 1

- [x] T035 [P] [US1] Crear `app/Http/Requests/Auth/LoginRequest.php` (research R-02, R-03, R-04): reglas `login` required|string|max:150 y `password` required|string; métodos estáticos puros `columnaPara(string $login): string` (devuelve `'email'` si contiene `@`, si no `'username'`) y `claveLimite(string $login, string $ip): string` (devuelve `'login|' . mb_strtolower(trim($login)) . '|' . $ip`); método `authenticate(): void` que: (1) calcula la clave con `claveLimite()` y si `RateLimiter::tooManyAttempts($clave, 5)` lanza `ValidationException` en `login` con "Demasiados intentos. Intenta nuevamente en :seconds segundos."; (2) usa la columna de `columnaPara()` con el valor en minúsculas; (3) si `Auth::attempt` falla → `RateLimiter::hit($clave, 60)` y `ValidationException` en `login` con "Las credenciales no son válidas."; (4) si el usuario autenticado tiene `activo=false` → `RateLimiter::hit($clave, 60)`, limpieza de sesión centralizada (`App\Support\Sesion::cerrarYReiniciar`) y `ValidationException` "Tu cuenta está inactiva. Contacta al administrador."; (5) si tiene éxito → `RateLimiter::clear($clave)`. **Cubre**: FR-001, FR-002, FR-006.
- [x] T036 [US1] Crear `app/Http/Controllers/Auth/LoginController.php` con `create()` (vista `auth.login`), `store(LoginRequest $request)` (`authenticate()`, `session()->regenerate()`, `redirect()->intended(route('inicio'))`) y `destroy(Request $request)` (`Auth::logout()`, `invalidate()`, `regenerateToken()`, redirect a `route('login')`) (depende de T035). **Cubre**: FR-001, FR-002, FR-003, FR-006.
- [x] T037 [P] [US1] Crear `app/Http/Middleware/EnsureUserIsActive.php`: si hay usuario autenticado con `activo=false` → `Auth::logout()`, invalidar sesión, regenerar token y `redirect()->route('login')->withErrors(['login' => 'Tu cuenta está inactiva. Contacta al administrador.'])`. **Cubre**: FR-004.
- [x] T038 [US1] En `bootstrap/app.php` registrar el alias de middleware `'activo' => EnsureUserIsActive::class`, `redirectGuestsTo(fn () => route('login'))` y `redirectUsersTo(fn () => route('inicio'))` (depende de T037). **Cubre**: FR-004, FR-005.
- [x] T039 [US1] Definir en `routes/web.php`: grupo `guest` con `GET /login` → `LoginController@create` (`login`) y `POST /login` → `LoginController@store` (`login.store`); grupo `['auth', 'activo']` con `POST /logout` (`logout`), `GET /` → redirect a `/inicio` y `GET /inicio` → vista `inicio` (`inicio`). Eliminar la ruta `welcome` y `resources/views/welcome.blade.php` (depende de T036, T038). **Cubre**: FR-005.
- [x] T040 [P] [US1] Crear `resources/views/auth/login.blade.php` (usa `layouts.guest`): formulario `POST {{ route('login.store') }}` con `@csrf`, campo `login` (etiqueta "Correo o nombre de usuario", `autofocus`, conserva `old('login')`), campo `password`, botón "Ingresar" y bloque que muestra `$errors->first('login')`. **Cubre**: FR-001, FR-002.
- [x] T041 [P] [US1] Crear `resources/views/inicio.blade.php` (usa `layouts.app`): saludo "Bienvenido, {{ nombre_completo }}" y texto placeholder para módulos futuros. **Cubre**: FR-005.
- [x] T042 [US1] Ejecutar `./vendor/bin/pest tests/Feature/Auth tests/Unit/Auth tests/Unit/Config` y corregir hasta que T029–T034 pasen. **Cubre**: FR-001 a FR-008, SC-001.

**Checkpoint**: Login/logout funcional e independiente; US1 entregable como MVP.

---

## Phase 4: User Story 2 - Autorización por permisos del rol (Priority: P1)

**Goal**: Toda decisión de acceso se resuelve contra `rol_permiso` en cada solicitud; menú filtrado
por permisos; acciones sin permiso → 403 (FR-009…FR-015).

**Independent Test**: Con permisos creados en el test, un usuario con `usuarios.ver` ve el ítem
"Usuarios" y accede a una ruta de prueba protegida con `can:usuarios.ver`; sin el permiso no ve el
ítem y recibe 403; quitar/otorgar el permiso surte efecto en la siguiente solicitud.

### Tests for User Story 2 ⚠️

- [x] T043 [P] [US2] Escribir `tests/Feature/Models/UsuarioTienePermisoTest.php`: `tienePermiso` es verdadero para un permiso del rol y falso para uno ajeno o inexistente; tras la primera llamada no ejecuta más consultas en la misma instancia (`DB::enableQueryLog`); una instancia nueva (`fresh()`) refleja cambios en `rol_permiso`. **Cubre**: FR-011, FR-015.
- [x] T044 [P] [US2] Escribir `tests/Feature/Autorizacion/GateTest.php`: registrar en el test una ruta `Route::middleware(['web', 'auth', 'activo', 'can:usuarios.ver'])->get('/_prueba-permiso', fn () => 'ok')`; usuario con `usuarios.ver` → 200; sin él → 403; `Gate::allows('permiso.inexistente')` → false; tras quitar el permiso al rol (`detach`) la siguiente solicitud del mismo usuario → 403 sin volver a iniciar sesión; tras otorgarlo (`attach`) → 200; cambiar el `rol_id` del usuario a un rol sin el permiso → 403 en la siguiente solicitud. **Cubre**: FR-011, FR-012, SC-002.
- [x] T045 [P] [US2] Escribir `tests/Feature/Autorizacion/MenuTest.php` solo con casos de ocultación (en US2 las rutas `admin.*` aún no existen; los casos positivos se agregan en US3 y US4): `GET /inicio` con un usuario sin permisos muestra el enlace "Inicio" y el menú no contiene "Usuarios", "Roles" ni "Permisos"; con un usuario que tiene `usuarios.ver` y `roles.ver` pero sin las rutas `admin.*` registradas, esos ítems tampoco aparecen (el menú oculta ítems cuya ruta no existe según `Route::has`) y la página responde 200 sin errores. **Cubre**: FR-013, SC-003.
- [x] T046 [P] [US2] Escribir `tests/Feature/Autorizacion/SinCondicionalesDeRolTest.php` (SC-005): recorrer los archivos de `app/`, `routes/`, `config/` y `resources/views/` y fallar si alguno contiene (sin distinguir mayúsculas) los literales `'Administrador'`, `'Director'`, `'Docente'`, `'Padre de Familia'`, `'Alumno'` (entre comillas simples o dobles) o expresiones como `->rol->nombre ==` / `rol->nombre ===`; `database/seeders/` queda excluido. Comentario en el test: "Si los módulos 2–6 necesitan 'Alumno'/'Docente' como texto de interfaz, refinar el patrón para detectar solo comparaciones de rol, sin relajar la regla." **Cubre**: FR-011, SC-005.
- [x] T047 [P] [US2] Escribir `tests/Feature/Autorizacion/Pagina403Test.php`: una respuesta 403 muestra la vista en español con el texto "No tienes permiso para realizar esta acción." y un enlace a `/inicio`. **Cubre**: FR-012.
- [x] T048 [P] [US2] Escribir `tests/Unit/Models/UsuarioTienePermisoEnMemoriaTest.php` (sin BD): construir `new Usuario(['activo' => true])` con `setRelation('rol', (new Rol)->setRelation('permisos', collect([new Permiso(['identificador' => 'usuarios.ver'])])))`; `tienePermiso('usuarios.ver')` → `true`; `tienePermiso('usuarios.crear')` → `false`; con `activo => false` y la misma relación, `tienePermiso('usuarios.ver')` → `false`. **Cubre**: FR-011.

### Implementation for User Story 2

- [x] T049 [US2] Añadir a `app/Models/Usuario.php` el método `tienePermiso(string $identificador): bool` con memoización en una propiedad protegida `?array $identificadoresPermiso = null` que se llena una sola vez con `$this->rol->permisos->pluck('identificador')->all()` (usa la relación ya cargada si existe, lo que permite probarlo en memoria con `setRelation`) (research R-05). **Si `$this->activo` es falso devuelve siempre `false`** (segunda defensa ante cuentas desactivadas con una página Livewire abierta). No cachear entre solicitudes. **Cubre**: FR-011, FR-015.
- [x] T050 [US2] En `app/Providers/AppServiceProvider.php::boot()` registrar `Gate::before(fn (Usuario $usuario, string $ability) => $usuario->tienePermiso($ability) ? true : null);` con un comentario que explique que devolver `null` hace que Laravel deniegue las habilidades no definidas (research R-05); y registrar `Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);` para que las peticiones de acciones Livewire también desconecten a cuentas desactivadas (research R-03) (depende de T049 y T037). **Cubre**: FR-011, FR-012.
- [x] T051 [P] [US2] Crear `config/menu.php` con los ítems de la tabla "Menú" de contracts/rutas-web.md: `['etiqueta' => 'Inicio', 'ruta' => 'inicio', 'permiso' => null]`, `Usuarios` → `admin.usuarios.index` / `usuarios.ver`, `Roles` → `admin.roles.index` / `roles.ver`, `Permisos` → `admin.permisos.index` / `roles.ver`. **Cubre**: FR-013.
- [x] T052 [US2] Implementar `resources/views/layouts/partials/menu.blade.php`: recorrer `config('menu')`, omitir ítems cuya ruta no exista (`Route::has`), mostrar los de `permiso` nulo y los demás solo dentro de `@can($item['permiso'])`; marcar el ítem activo con `request()->routeIs($item['ruta'].'*')` (depende de T051). **Cubre**: FR-013.
- [x] T053 [P] [US2] Crear `resources/views/errors/403.blade.php` en español: título "Acceso denegado", texto "No tienes permiso para realizar esta acción." y enlace "Volver al inicio" a `/inicio`. **Cubre**: FR-012.
- [x] T054 [US2] Ejecutar `./vendor/bin/pest tests/Feature/Models/UsuarioTienePermisoTest.php tests/Feature/Autorizacion tests/Unit/Models` y corregir hasta que T043–T048 pasen. **Cubre**: FR-011, FR-012, FR-013, SC-005.

**Checkpoint**: RBAC por tabla operativo para cualquier ruta o acción futura; US1 sigue en verde.

---

## Phase 5: User Story 5 - Datos iniciales de roles y permisos (Priority: P1)

**Goal**: Una instalación nueva queda operativa con 5 roles, 7 permisos base, sus asignaciones y
un Administrador inicial desde el entorno (FR-023, FR-024, SC-008).

**Independent Test**: `migrate:fresh --seed` dos veces seguidas: sin duplicados, 5 roles, 7
permisos, asignaciones de research R-07, y el Administrador inicial inicia sesión y tiene
`usuarios.editar` y `roles.asignar_permisos`.

### Tests for User Story 5 ⚠️

- [x] T055 [P] [US5] Escribir `tests/Feature/Seeders/RolesYPermisosSeederTest.php`: tras `seed(RolesYPermisosSeeder::class)` existen exactamente los roles "Administrador", "Director", "Docente", "Padre de Familia", "Alumno" y los 7 identificadores `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.desactivar`, `roles.ver`, `permisos.gestionar`, `roles.asignar_permisos`; Administrador tiene los 7; Director tiene solo `usuarios.ver` y `roles.ver`; Docente, Padre de Familia y Alumno no tienen ninguno; ejecutar el seeder dos veces no duplica filas; si tras la primera ejecución se quita `usuarios.ver` al Director, una segunda ejecución NO lo vuelve a asignar. **Cubre**: FR-023.
- [x] T056 [P] [US5] Escribir `tests/Feature/Seeders/AdministradorInicialSeederTest.php`: con `config(['sge.admin' => [...]])` crea un usuario activo con ese username/email y rol Administrador; puede hacer `POST /login` con esa contraseña y `Gate::forUser($admin)->allows('roles.asignar_permisos')` es verdadero; si el usuario ya existe con otra contraseña, re-ejecutar el seeder no la cambia; si `sge.admin.password` está vacío lanza `RuntimeException` con mensaje que menciona `SGE_ADMIN_PASSWORD`; si tiene menos de 8 caracteres lanza `RuntimeException` y no crea el usuario. **Cubre**: FR-024, SC-008.
- [x] T057 [P] [US5] Escribir `tests/Feature/Seeders/DatabaseSeederTest.php`: `seed()` completo deja 5 roles, 7 permisos y el Administrador inicial; con `app()->detectEnvironment(fn () => 'testing')` no crea los usuarios demo. **Cubre**: FR-023, FR-024, SC-008.
- [x] T058 [P] [US5] Escribir `tests/Unit/Seeders/RolesYPermisosDefinicionTest.php` (sin BD): `RolesYPermisosSeeder::ROLES` tiene exactamente 5 nombres ("Administrador", "Director", "Docente", "Padre de Familia", "Alumno"); `RolesYPermisosSeeder::PERMISOS` tiene exactamente los 7 identificadores de research R-07; Administrador aparece en los 7; Director solo en `usuarios.ver` y `roles.ver`; Docente, Padre de Familia y Alumno en ninguno. **Cubre**: FR-023.

### Implementation for User Story 5

- [x] T059 [P] [US5] Crear `database/seeders/RolesYPermisosSeeder.php` con dos constantes públicas `ROLES` y `PERMISOS`: los 5 roles (nombre + descripción) y los 7 permisos base (identificador, nombre visible y roles que lo reciben, según la tabla de research R-07). Usar `Rol::firstOrCreate(['nombre' => ...])` y `Permiso::firstOrCreate(['identificador' => ...], [...])`; asignar el permiso a sus roles **solo si** `$permiso->wasRecentlyCreated` (data-model.md "Nota sobre idempotencia"). **Cubre**: FR-023.
- [x] T060 [P] [US5] Crear `database/seeders/AdministradorInicialSeeder.php`: leer `config('sge.admin')`; si `password` está vacío lanzar `RuntimeException('Define SGE_ADMIN_PASSWORD en .env antes de ejecutar los seeders.')`; si tiene menos de 8 caracteres lanzar `RuntimeException('SGE_ADMIN_PASSWORD debe tener al menos 8 caracteres.')` (FR-007); `Usuario::firstOrCreate(['username' => ...], ['nombres' => 'Administrador', 'apellidos' => 'del Sistema', 'email' => ..., 'password' => ..., 'rol_id' => Rol::where('nombre', 'Administrador')->value('id'), 'activo' => true])` (el nombre de rol se permite aquí porque es un seeder). **Cubre**: FR-024.
- [x] T061 [P] [US5] Crear `database/seeders/UsuariosDemoSeeder.php`: un usuario activo por rol (`director.demo`, `docente.demo`, `padre.demo`, `alumno.demo`, contraseña `password`), idempotente con `firstOrCreate`. **Cubre**: — (datos de demostración; sin FR/SC propios).
- [x] T062 [US5] Actualizar `database/seeders/DatabaseSeeder.php` para llamar a `RolesYPermisosSeeder` y `AdministradorInicialSeeder`, y a `UsuariosDemoSeeder` solo si `app()->environment('local')` (depende de T059–T061). **Cubre**: FR-023, FR-024.
- [x] T063 [US5] Ejecutar `./vendor/bin/pest tests/Feature/Seeders tests/Unit/Seeders` y `php artisan migrate:fresh --seed && php artisan db:seed` en el contenedor; corregir hasta que T055–T058 pasen y la segunda siembra no falle. **Cubre**: FR-023, FR-024, SC-008.

**Checkpoint**: Instalación reproducible desde cero; el Administrador inicial puede entrar.

---

## Phase 6: User Story 3 - Gestión de cuentas de usuario por el Administrador (Priority: P2)

**Goal**: Listar, crear, editar, cambiar rol, restablecer contraseña, desactivar y reactivar
usuarios, con permisos y protección de la última cuenta administradora (FR-016…FR-018, FR-021,
FR-022).

**Independent Test**: Como usuario con los 4 permisos `usuarios.*` (+ protección con dos admins),
crear un Docente, iniciar sesión con él, cambiarle el rol, desactivarlo y verificar que no puede
entrar; cada acción con un usuario sin el permiso → 403.

### Tests for User Story 3 ⚠️

- [x] T064 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/IndexTest.php`: `GET /admin/usuarios` con `usuarios.ver` → 200 y lista usuarios con nombre completo, username, email, rol y estado; sin permiso → 403; `Livewire::test(Index::class)->set('q', 'texto')` filtra por nombres, apellidos, username o email; pagina de 15 en 15. **Cubre**: FR-012, FR-016, SC-002.
- [x] T065 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/CrearUsuarioTest.php`: `GET /admin/usuarios/crear` con `usuarios.crear` → 200, sin él → 403; `Livewire::test(Form::class)->set(...)->call('guardar')` crea un usuario activo con email opcional; username/email se guardan en minúsculas; errores de validación para: username duplicado (sin distinguir mayúsculas), email duplicado, username con `@` o fuera de `^[a-z0-9._-]{3,50}$`, password < 8 o sin confirmar, `rol_id` inexistente, nombres/apellidos vacíos o > 100; sin `usuarios.crear` `call('guardar')` → `assertForbidden()` y no se crea nada; el usuario creado puede hacer `POST /login`. **Cubre**: FR-016, FR-017, FR-021.
- [x] T066 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/EditarUsuarioTest.php`: `GET /admin/usuarios/{id}/editar` con `usuarios.editar` → 200, sin él → 403; editar datos respeta unicidad ignorando al propio usuario; cambiar `rol_id` hace que en la siguiente solicitud el usuario tenga los permisos del nuevo rol; cambiar el rol de la única cuenta administradora a un rol sin permisos de administración → error de validación en `rol_id` y el rol no cambia; `restablecerPassword` con `usuarios.editar` cambia la contraseña (la anterior ya no permite login, la nueva sí) y valida mínimo 8 + confirmación; sin `usuarios.editar`, `guardar` y `restablecerPassword` → 403. **Cubre**: FR-015, FR-016, FR-017, FR-018, FR-021, FR-022.
- [x] T067 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/DesactivarUsuarioTest.php`: con `usuarios.desactivar`, `Livewire::test(Index::class)->call('desactivar', $id)` pone `activo=false` y ese usuario ya no puede iniciar sesión (mensaje de cuenta inactiva); `reactivar` lo vuelve a habilitar; desactivar la propia cuenta → error "No puedes desactivar tu propia cuenta" y sigue activa; desactivar la única cuenta administradora → error y sigue activa; sin `usuarios.desactivar` ambas acciones → 403. **Cubre**: FR-003, FR-004, FR-016, FR-022.
- [x] T068 [P] [US3] Escribir `tests/Unit/Validacion/UsernameReglaTest.php` (sin BD, dataset de Pest sobre `Usuario::REGEX_USERNAME`): aceptados `ana`, `ana.perez`, `a_b-c.1`, 50 caracteres; rechazados `an` (2), 51 caracteres, `ana@colegio.pe`, `Ana` (mayúscula), `ana perez` (espacio), `ñandu`. **Cubre**: FR-021.
- [x] T069 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/CuentaDesactivadaLivewireTest.php` (C1): un usuario activo con `usuarios.ver` y `usuarios.desactivar` monta `$c = Livewire::actingAs($u)->test(Index::class)`; luego se ejecuta `$u->update(['activo' => false])` (misma instancia autenticada) y `$c->call('desactivar', $otro->id)` recibe `assertForbidden()` y `$otro` sigue activo (segunda defensa de `tienePermiso`); además `expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserIsActive::class)` (método público de `LivewireManager`, verificado en el código de Livewire v4.4.6; es la única prueba de que el middleware quedó registrado, porque `Livewire::test()` no pasa por el middleware HTTP y el 403 anterior lo produce la segunda defensa de `tienePermiso`). **Cubre**: FR-004.

### Implementation for User Story 3

- [x] T070 [US3] Crear `app/Livewire/Admin/Usuarios/Index.php` (componente de clase, `WithPagination`) y `resources/views/livewire/admin/usuarios/index.blade.php`: `mount()` con `$this->authorize('usuarios.ver')`; propiedad `#[Url] public string $q = ''` enlazada con `wire:model.live.debounce.300ms="q"`; consulta con `with('rol')`, filtro por prefijo (`LIKE 'texto%'`) en nombres, apellidos, username y email, y `paginate(15)` (reglas de rendimiento); acciones `desactivar(int $id)` y `reactivar(int $id)` que llaman `$this->authorize('usuarios.desactivar')`, rechazan la propia cuenta y consultan `ProteccionAdministracion::puedeDesactivar()` (errores con `addError('general', ...)`); botones "Crear usuario", "Editar", "Desactivar"/"Reactivar" envueltos en `@can` con su permiso (contracts/componentes-livewire.md, `Admin\Usuarios\Index`). **Cubre**: FR-016.
- [x] T071 [US3] Crear `app/Livewire/Admin/Usuarios/Form.php` y `resources/views/livewire/admin/usuarios/form.blade.php`: `mount(?Usuario $usuario = null)` autoriza `usuarios.crear` (nuevo) o `usuarios.editar` (existente); propiedades `nombres`, `apellidos`, `username`, `email`, `password`, `password_confirmation`, `rol_id`; `guardar()` vuelve a autorizar, normaliza `username`/`email` a minúsculas y valida: nombres/apellidos `required|string|max:100`; username `required|regex:` + `Usuario::REGEX_USERNAME` + `|unique:usuarios,username,{id}`; email `nullable|email|max:150|unique:usuarios,email,{id}`; password `required|min:8|confirmed` solo al crear; `rol_id` `required|exists:roles,id`; al editar con cambio de rol consulta `ProteccionAdministracion::puedeCambiarRol()` y, si falla, `addError('rol_id', 'No se puede cambiar el rol: el sistema quedaría sin cuentas administradoras.')`; tras guardar redirige a `admin.usuarios.index`. `restablecerPassword()` autoriza `usuarios.editar` y valida `password` `required|min:8|confirmed`. El selector de rol lista `Rol::orderBy('nombre')` (depende de T070 solo por la ruta de regreso). **Cubre**: FR-016, FR-017, FR-018, FR-021, FR-022, SC-007.
- [x] T072 [US3] Registrar en `routes/web.php`, dentro del grupo `['auth', 'activo']`: `Route::livewire('/admin/usuarios', Index::class)->name('admin.usuarios.index')->middleware('can:usuarios.ver')`, `Route::livewire('/admin/usuarios/crear', Form::class)->name('admin.usuarios.create')->middleware('can:usuarios.crear')` y `Route::livewire('/admin/usuarios/{usuario}/editar', Form::class)->name('admin.usuarios.edit')->middleware('can:usuarios.editar')` (depende de T070, T071). **Cubre**: FR-012, FR-016.
- [x] T073 [US3] Agregar a `tests/Feature/Autorizacion/MenuTest.php` los casos positivos de US3: un usuario con `usuarios.ver` ve el enlace "Usuarios" apuntando a `/admin/usuarios`; uno sin `usuarios.ver` no lo ve. Luego ejecutar `./vendor/bin/pest tests/Feature/Admin/Usuarios tests/Feature/Autorizacion tests/Unit/Validacion/UsernameReglaTest.php` y corregir hasta que T064–T069 y MenuTest pasen. **Cubre**: FR-013, SC-003.
- [x] T103 [US3] Usabilidad del formulario de creación de usuarios para cumplir SC-007 (< 1 min; medido 1 min 10 s en local, ver `evidencias/verificacion-manual.md`). **Pruebas primero** (se escribieron y fallaron antes de implementar): `tests/Unit/Models/SugerirUsernameTest.php` y los casos T103 al final de `tests/Feature/Admin/Usuarios/CrearUsuarioTest.php`. Implementación: (1) `Usuario::sugerirUsername()` = primer nombre + `.` + primer apellido, minúsculas, sin tildes, ñ→n, solo `REGEX_USERNAME` (vacío si no es válido); `Form` lo aplica en `updatedNombres/updatedApellidos` solo al crear y mientras `usernameEditado` sea falso (se activa al escribir `username` a mano; vaciarlo retoma la sugerencia); siempre editable; si ya existe se mantiene el error `unique` al guardar, sin numeración automática; (2) `autofocus` en «Nombres» solo al crear; (3) correo opcional movido después del rol. Mejora 4 revisada: el formulario no usaba `wire:model.live`, nada que cambiar (`nombres`, `apellidos` y `username` usan `wire:model.blur` para la sugerencia). Sin cambios de spec. **Cubre**: SC-007, FR-016, FR-021.

**Checkpoint**: Gestión de usuarios completa e independiente de US4.

---

## Phase 7: User Story 4 - Gestión de permisos y asignación a roles (Priority: P2)

**Goal**: Catálogo de permisos (crear, editar nombre/descripción), lista de roles y edición de los
permisos de cada rol, con protección de la última cuenta administradora (FR-014, FR-019, FR-020,
FR-022).

**Independent Test**: Quitar `usuarios.ver` al rol de un usuario de prueba desde
`Admin\Roles\Permisos` → su siguiente solicitud a una ruta con `can:usuarios.ver` responde 403;
restaurarlo → 200; crear y editar un permiso; cada acción sin permiso → 403.

### Tests for User Story 4 ⚠️

- [x] T074 [P] [US4] Escribir `tests/Feature/Admin/Permisos/IndexTest.php`: `GET /admin/permisos` con `roles.ver` → 200 y lista identificador, nombre y descripción; sin permiso → 403; con `permisos.gestionar`, `call('crear')` con identificador válido crea el permiso; errores para identificador duplicado, identificador que no cumple `^[a-z_]+(\.[a-z_]+)+$`, nombre vacío o > 100, descripción > 255; `call('actualizar', $id)` cambia nombre y descripción y el identificador queda igual (aunque se intente enviar otro); con solo `roles.ver`, `crear` y `actualizar` → 403. **Cubre**: FR-012, FR-014, FR-019.
- [x] T075 [P] [US4] Escribir `tests/Feature/Admin/Roles/IndexTest.php`: `GET /admin/roles` con `roles.ver` → 200 y muestra cada rol con su cantidad de usuarios y de permisos; sin permiso → 403. **Cubre**: FR-010.
- [x] T076 [P] [US4] Escribir `tests/Feature/Admin/Roles/PermisosDeRolTest.php`: `GET /admin/roles/{id}/permisos` con `roles.ver` → 200 con casillas marcadas según `rol_permiso`; sin permiso → 403; con `roles.asignar_permisos`, `set('seleccionados', [...])->call('guardar')` sincroniza `rol_permiso`; un usuario de ese rol recibe 403/200 en su siguiente solicitud según el cambio (sin reiniciar sesión); quitar `roles.asignar_permisos` o `usuarios.editar` del rol de la única cuenta administradora → error y `rol_permiso` sin cambios; ids inexistentes → error de validación; con solo `roles.ver`, `guardar` → 403. **Cubre**: FR-015, FR-020, FR-022, SC-004.
- [x] T077 [P] [US4] Escribir `tests/Unit/Validacion/IdentificadorPermisoReglaTest.php` (sin BD, dataset de Pest sobre `Permiso::REGEX_IDENTIFICADOR`): aceptados `usuarios.ver`, `roles.asignar_permisos`, `a.b.c`; rechazados `usuarios` (sin punto), `Usuarios.ver`, `usuarios.ver.`, `.ver`, `usuarios-ver`, `usuarios.ver1`. **Cubre**: FR-014.

### Implementation for User Story 4

- [x] T078 [P] [US4] Crear `app/Livewire/Admin/Permisos/Index.php` y `resources/views/livewire/admin/permisos/index.blade.php`: `mount()` autoriza `roles.ver`; lista de permisos ordenada por identificador; formulario de creación (`identificador`, `nombre`, `descripcion`) y edición en línea (`nombre`, `descripcion`) visibles solo con `@can('permisos.gestionar')`; `crear()` autoriza `permisos.gestionar` y valida identificador `required|max:100|regex:` + `Permiso::REGEX_IDENTIFICADOR` + `|unique:permisos,identificador`, nombre `required|string|max:100`, descripcion `nullable|string|max:255`; `actualizar(int $id)` autoriza `permisos.gestionar`, valida solo nombre y descripcion y hace `update(['nombre' => ..., 'descripcion' => ...])` (nunca el identificador). **Cubre**: FR-014, FR-019.
- [x] T079 [P] [US4] Crear `app/Livewire/Admin/Roles/Index.php` y `resources/views/livewire/admin/roles/index.blade.php`: `mount()` autoriza `roles.ver`; lista de roles con `withCount(['usuarios', 'permisos'])`, nombre, descripción y enlace "Permisos" a `admin.roles.permisos`. **Cubre**: FR-010.
- [x] T080 [US4] Crear `app/Livewire/Admin/Roles/Permisos.php` y `resources/views/livewire/admin/roles/permisos.blade.php`: `mount(Rol $rol)` autoriza `roles.ver` y carga `seleccionados` con los ids actuales; casillas de todos los permisos (deshabilitadas sin `@can('roles.asignar_permisos')`); `guardar()` autoriza `roles.asignar_permisos`, valida `seleccionados` `array` y cada id `exists:permisos,id`, consulta `ProteccionAdministracion::puedeSincronizarPermisos($rol, $seleccionados)` y si falla `addError('seleccionados', 'No se puede guardar: el sistema quedaría sin cuentas administradoras.')`; si pasa, `$rol->permisos()->sync($seleccionados)` y mensaje de éxito. **Cubre**: FR-015, FR-020, FR-022, SC-004.
- [x] T081 [US4] Registrar en `routes/web.php`, dentro del grupo `['auth', 'activo']`: `Route::livewire('/admin/permisos', Permisos\Index::class)->name('admin.permisos.index')->middleware('can:roles.ver')`, `Route::livewire('/admin/roles', Roles\Index::class)->name('admin.roles.index')->middleware('can:roles.ver')` y `Route::livewire('/admin/roles/{rol}/permisos', Roles\Permisos::class)->name('admin.roles.permisos')->middleware('can:roles.ver')` (depende de T078–T080). **Cubre**: FR-012.
- [x] T082 [US4] Agregar a `tests/Feature/Autorizacion/MenuTest.php`: (1) casos positivos de US4 — con `roles.ver` se ven "Roles" y "Permisos"; sin él no; (2) SC-003 — tras `seed(RolesYPermisosSeeder::class)`, un caso por cada uno de los 5 roles sembrados (dataset de Pest) que compare los ítems visibles con la tabla "Visibilidad esperada" de contracts/rutas-web.md (Administrador y Director: Inicio, Usuarios, Roles, Permisos; Docente, Padre de Familia y Alumno: solo Inicio). Va aquí y no en US2/US5 porque requiere que existan todas las rutas `admin.*`. Luego ejecutar `./vendor/bin/pest tests/Feature/Admin/Permisos tests/Feature/Admin/Roles tests/Feature/Autorizacion tests/Unit/Validacion/IdentificadorPermisoReglaTest.php` y corregir hasta que T074–T077 y MenuTest pasen. **Cubre**: FR-013, SC-003.

**Checkpoint**: Política de acceso configurable desde la interfaz sin cambios de código.

---

## Phase 8: User Story 6 - Punto de extensión para filtros de pertenencia (Priority: P3)

**Goal**: Mecanismo reutilizable y fail-closed para restringir registros por relación con el
usuario, listo para los módulos 2–6 (FR-025…FR-028).

**Independent Test**: Con `RegistroDePrueba` + `ReglaDePrueba` (solo en `tests/`): con regla,
`visiblePara()` filtra y un registro ajeno da 403; sin regla, cualquier consulta da 403; un modelo
sin el trait no se altera.

### Tests for User Story 6 ⚠️

- [x] T083 [P] [US6] Crear soporte de pruebas en `tests/Support/Pertenencia/`: `RegistroDePrueba.php` (modelo Eloquent con tabla `registros_de_prueba`: `id`, `usuario_id`, `titulo`; usa el trait `RequierePertenencia`), `ReglaDePrueba.php` (implementa `ReglaPertenencia`: `aplicar` filtra `where('usuario_id', $usuario->id)`, `permite` compara `usuario_id`) y `ModeloSinPertenencia.php` (modelo con tabla `modelos_sin_pertenencia`, sin el trait). Las tablas se crean con `Schema::create` en el `beforeEach` de las pruebas que las usan. **Cubre**: FR-028.
- [x] T084 [P] [US6] Escribir `tests/Feature/Pertenencia/MecanismoPertenenciaTest.php` con los 4 casos de contracts/pertenencia.md "Pruebas que acompañan este contrato" más: con regla registrada (`config(['pertenencia.reglas' => [RegistroDePrueba::class => ReglaDePrueba::class]])`) un usuario sin registros propios obtiene colección vacía (no error); sin regla, `RegistroDePrueba::query()->visiblePara($u)->get()` lanza `ReglaPertenenciaFaltante` y, dentro de una ruta de prueba, la respuesta es 403; `autorizarPertenencia()` sin regla → 403. **Cubre**: FR-025, FR-026, FR-027, FR-028.
- [x] T085 [P] [US6] Escribir `tests/Unit/Autorizacion/RegistroReglasPertenenciaTest.php` (Unit: no toca la BD, solo `config()` y el contenedor): `reglaPara(RegistroDePrueba::class)` devuelve una instancia de `ReglaDePrueba` cuando está configurada y `null` cuando no; una clase configurada que no implementa `ReglaPertenencia` lanza `InvalidArgumentException`; `ReglaPertenenciaFaltante` es instancia de `Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException`. **Cubre**: FR-026, FR-028.

### Implementation for User Story 6

- [x] T086 [P] [US6] Crear `app/Autorizacion/Pertenencia/ReglaPertenencia.php` con la interfaz exacta de contracts/pertenencia.md (`aplicar(Builder $query, Usuario $usuario): Builder` y `permite(Usuario $usuario, Model $registro): bool`) y un docblock con las restricciones "MUST NOT comparar el nombre del rol". **Cubre**: FR-025, FR-026.
- [x] T087 [P] [US6] Crear `app/Autorizacion/Pertenencia/ReglaPertenenciaFaltante.php` extendiendo `AccessDeniedHttpException`, con constructor que recibe el nombre de la clase del modelo y usa el mensaje "No hay regla de pertenencia registrada para {Modelo}; acceso denegado por defecto.". **Cubre**: FR-027.
- [x] T088 [P] [US6] Crear `config/pertenencia.php` que devuelva `['reglas' => []]` con un comentario de ejemplo `// App\Models\Alumno::class => App\Autorizacion\Pertenencia\Reglas\AlumnoRegla::class`. **Cubre**: FR-026.
- [x] T089 [US6] Crear `app/Autorizacion/Pertenencia/RegistroReglasPertenencia.php`: método `reglaPara(string $modelo): ?ReglaPertenencia` que lee `config('pertenencia.reglas')`, instancia la clase con el contenedor y lanza `InvalidArgumentException` si no implementa la interfaz. Registrarlo como singleton en `app/Providers/AppServiceProvider.php::register()` (depende de T086). **Cubre**: FR-026.
- [x] T090 [US6] Crear `app/Autorizacion/Pertenencia/RequierePertenencia.php` (trait): `scopeVisiblePara(Builder $query, Usuario $usuario): Builder` que obtiene la regla del registro y lanza `ReglaPertenenciaFaltante` si es `null`, o devuelve `$regla->aplicar($query, $usuario)`; y `autorizarPertenencia(Usuario $usuario): void` que lanza `ReglaPertenenciaFaltante` sin regla o `AccessDeniedHttpException` si `permite()` es falso (depende de T087, T089). **Cubre**: FR-027.
- [x] T091 [US6] Ejecutar `./vendor/bin/pest tests/Feature/Pertenencia tests/Unit/Autorizacion` y corregir hasta que T083–T085 pasen. **Cubre**: FR-028.

**Checkpoint**: Punto de extensión probado; los módulos futuros solo añaden el trait y una regla.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Prueba de carga del login, verificación global de rutas protegidas, documentación y
validación final.

- [x] T092 [P] Escribir `tests/Feature/Autorizacion/RutasProtegidasTest.php` (SC-002): recorrer `Route::getRoutes()` y, para cada ruta con middleware `can:*`, comprobar que un usuario activo sin permisos recibe 403 y un usuario con exactamente ese permiso no recibe 403 (sustituir parámetros `{usuario}`/`{rol}` por registros de factory). **Cubre**: FR-012, SC-002.
- [x] T101 [P] Escribir `tests/Feature/Seeders/LoadTestSeederTest.php` (antes de T093): con `APP_ENV=testing`, ejecutar `LoadTestSeeder` crea exactamente 1000 usuarios `carga0001`…`carga1000` activos con rol `Carga` que tiene solo `usuarios.ver`, y una segunda ejecución no duplica; con `app()->detectEnvironment(fn () => 'production')` el seeder lanza excepción y no crea ningún usuario; `DatabaseSeeder` no crea usuarios `carga*`. **Cubre**: SC-006.
- [x] T102 [P] Escribir `tests/Feature/Rendimiento/ListadoUsuariosTest.php` (reglas de rendimiento): con 5 y con 40 usuarios, `GET /admin/usuarios` ejecuta el mismo número de consultas (`DB::enableQueryLog()`, sin N+1) y muestra como máximo 15 filas; `Schema::getIndexes('usuarios')` incluye índices sobre `rol_id`, `activo`, `nombres`, `apellidos`, `username` y `email`; `Schema::getIndexes('rol_permiso')` cubre `permiso_id`. **Cubre**: SC-006.
- [x] T093 [P] Crear `database/seeders/LoadTestSeeder.php`: rol `Carga` con `firstOrCreate(['nombre' => 'Carga'])` y solo el permiso `usuarios.ver` (ejecuta antes `RolesYPermisosSeeder`); 1000 usuarios activos `carga0001`…`carga1000` con contraseña `password`; el hash bcrypt se calcula **una sola vez** (`Hash::make`) y se reutiliza, insertando por lotes de 250 con `upsert` por `username` (idempotente); al inicio de `run()` lanza `RuntimeException` si `app()->environment('production')`; no se llama desde `DatabaseSeeder` (data-model.md, tabla de seeders; depende de T101). **Cubre**: SC-006.
- [x] T094 [P] Crear `tests/Load/carga.js` (research R-12): `const BASE_URL = __ENV.BASE_URL || 'http://web'`; escenario `ramping-vus` con etapas 0→100 (1m), 100 (1m), →300 (1m30s), 300 (1m), →600 (2m), 600 (1m), →1000 (2m30s), 1000 (3m), →0 (1m); cada VU inicia sesión **solo en su primera iteración** (`GET /login`, extrae `_token`, `POST /login` con `login=carga{__VU a 4 dígitos}` y `password=password`, etiqueta `{ name: 'login' }`, verifica que termina en `/inicio`) y en cada iteración siguiente consulta `GET /inicio` y `GET /admin/usuarios?q=…&page=N` (etiqueta `{ name: 'consulta' }`) con `sleep(10 + Math.random() * 20)` entre iteraciones; `thresholds`: `'http_req_duration{name:login}': ['p(95)<3500']`, `'http_req_duration{name:consulta}': ['p(95)<1500']`, `http_req_failed: ['rate<0.01']`. **Cubre**: SC-006.
- [x] T095 Ejecutar la prueba de carga (quickstart.md §4) en el entorno de pruebas con la pila de producción (OPcache con `validate_timestamps=0`, `php artisan optimize`): `php artisan db:seed --class=LoadTestSeeder` y `docker compose --profile load run --rm k6 run /scripts/carga.js` (o con `-e BASE_URL=…` contra el servidor desplegado en `APP_ENV=staging`, nunca producción); guardar el resumen en `specs/001-auth-roles-rbac/evidencias/k6-carga.txt` junto con CPU/memoria observadas. Si algún umbral falla, registrar el resultado e investigar (pool FPM, consultas, Redis) antes de dar el módulo por terminado; cambiar un umbral exige justificación escrita en la evidencia (depende de T093, T094, T099, T100 y US1–US3). **Estado: HECHA (2026-10-25).** Rampa completa de 1000 VUs contra el servidor desplegado (B2als_v2, v0.2.2) en dos sesiones con `evidencia_completa: true`: 24/10 (login p95 1,88 s, consultas p95 387 ms, 0 de 90 928 fallidas) y 25/10 (1,35 s, 243 ms, 0 de 91 100), en `evidencias/k6-sesion-2026-10-24.md` y `evidencias/k6-sesion-2026-10-25.md`; el humo local sigue en `evidencias/k6-humo.txt`. **Cubre**: SC-006.
- [x] T096 [P] Crear `README.md` en la raíz: requisitos, pasos de quickstart.md §1 (levantar, `.env`, `migrate:fresh --seed`, `npm run build`), cómo ejecutar pruebas y la prueba de carga, la regla "los permisos se verifican por identificador, nunca por nombre de rol" y el despliegue en producción (nginx + PHP-FPM, `OPCACHE_VALIDATE_TIMESTAMPS=0`, `php artisan optimize` en cada despliegue, Redis; sin Octane) con la advertencia de que `LoadTestSeeder` nunca se ejecuta en producción. **Cubre**: SC-008.
- [x] T097 Ejecutar la suite completa `./vendor/bin/pest` y la verificación manual de quickstart.md §3 (10 pasos) y §5 (reconstrucción desde cero); corregir cualquier fallo. **Estado: verificada en local el 2026-10-14** (suite completa en verde y todos los pasos de §3 recorridos en navegador; §5 verificado; detalle y el incumplimiento de SC-007 en `evidencias/verificacion-manual.md`). **Debe repetirse en el servidor desplegado.** **Cubre**: SC-001 a SC-008.
- [x] T098 Revisar el Definition of Done de la constitución para el módulo: todas las pruebas en verde, T046 en verde (sin nombres de rol en código), evidencia k6 guardada (1000 VUs), reglas de rendimiento verificadas (T102), `.env.example` actualizado y `docker compose up` sin pasos no documentados. **Estado: REVISADO (2026-10-25).** Pruebas y Pint en verde en el CI (run 40141430056, commit 6f912d4, que incluye T046 y T102); evidencia k6 de 1000 VUs guardada (T095, dos sesiones con `evidencia_completa: true`); `.env.example` al día; `docker compose up` documentado en el README y verificado en T097. **Cubre**: SC-005, SC-006.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; BLOQUEA todas las historias.
- **US1 (Phase 3)**: depende solo de Foundational.
- **US2 (Phase 4)** y **US5 (Phase 5)**: dependen de Foundational y de **US1** (US2 usa el alias `activo`, `/inicio` y el layout con logout; US5 prueba el login del Administrador inicial con `POST /login`).
- **US3 (Phase 6)** y **US4 (Phase 7)**: dependen de Foundational y de **US2** (necesitan
  `Gate::before` para que `authorize()` y `can:` consulten `rol_permiso`) y de **US1** (grupo de
  rutas `['auth', 'activo']` y layout con logout).
- **US6 (Phase 8)**: depende solo de Foundational (usa el modelo `Usuario`).
- **Polish (Phase 9)**: depende de todas las historias; T095 depende de T093–T094, T099–T100 y de US1–US3 (usa `/admin/usuarios`); T101 antecede a T093.

### User Story Dependencies

```text
Setup ─▶ Foundational ─┬─▶ US1 ─┬─▶ US2 ─┬─▶ US3
                       │        │        └─▶ US4
                       │        └─▶ US5
                       └─▶ US6
                                  (todas) ─▶ Polish
```

### Within Each User Story

- Pruebas primero; deben fallar antes de implementar.
- Modelos → servicios → componentes/controladores → rutas → ejecución de la suite de la historia.
- Cada historia termina con una tarea "Ejecutar … y corregir" que es su criterio de terminado.

### Parallel Opportunities

- Setup: T003, T004 en paralelo tras T002; T007, T008, T010, T011, T012 en paralelo tras T006.
- Foundational: T014 ∥ T015; T018 ∥ T019; T022 ∥ T023; T025 ∥ T026 ∥ T027.
- Tras Foundational: US1 y US6 en paralelo; tras US1: US2 y US5 en paralelo; tras US2: US3 y US4
  en paralelo entre sí.
- Dentro de cada historia, todas las pruebas marcadas [P] en paralelo.

---

## Parallel Example: User Story 1

```bash
# Pruebas de US1 en paralelo:
Task: "T029 tests/Feature/Auth/LoginTest.php"
Task: "T030 tests/Feature/Auth/CuentaInactivaTest.php"
Task: "T031 tests/Feature/Auth/LimiteIntentosTest.php"
Task: "T032 tests/Feature/Auth/LogoutTest.php"

# Implementación independiente en paralelo:
Task: "T035 app/Http/Requests/Auth/LoginRequest.php"
Task: "T037 app/Http/Middleware/EnsureUserIsActive.php"
Task: "T040 resources/views/auth/login.blade.php"
Task: "T041 resources/views/inicio.blade.php"
```

## Parallel Example: User Story 4

```bash
Task: "T074 tests/Feature/Admin/Permisos/IndexTest.php"
Task: "T075 tests/Feature/Admin/Roles/IndexTest.php"
Task: "T076 tests/Feature/Admin/Roles/PermisosDeRolTest.php"
Task: "T078 app/Livewire/Admin/Permisos/Index.php"
Task: "T079 app/Livewire/Admin/Roles/Index.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup → Phase 2: Foundational.
2. Phase 3: US1 (login/logout).
3. **STOP and VALIDATE**: `./vendor/bin/pest tests/Feature/Auth` en verde; login manual con un usuario
   creado en `tinker`.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → login (MVP).
3. US2 → RBAC por tabla + menú.
4. US5 → instalación reproducible con Administrador inicial (ya se puede demostrar sin `tinker`).
5. US3 → gestión de usuarios.
6. US4 → gestión de permisos por rol.
7. US6 → punto de extensión de pertenencia.
8. Polish → k6, README, validación final (requisito para considerar el módulo listo para despliegue).

### Solo Developer Strategy (plazo de 2 meses)

Orden secuencial recomendado: Phase 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9, con un commit al terminar
cada tarea "Ejecutar … y corregir".

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- **Cubre**: al final de cada tarea indica los FR-/SC- de spec.md que implementa o verifica; «—» marca tareas de infraestructura o soporte sin requisito propio
- Verificar que las pruebas fallan antes de implementar
- Commit tras cada tarea o grupo lógico
- Nunca introducir comparaciones por nombre de rol; T046 lo verifica automáticamente
- Evitar: tareas vagas, conflictos en el mismo archivo (`routes/web.php` y `AppServiceProvider.php` se tocan en varias historias: no ejecutar esas tareas en paralelo)
