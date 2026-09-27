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
prueba de carga k6 para el login. En cada historia las pruebas se escriben primero y deben fallar
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

- [ ] T001 Crear el proyecto Laravel 13 en la raíz del repositorio: ejecutar `composer create-project laravel/laravel:^13.0 _tmp_app` (con la imagen `composer:2` si aún no existe el contenedor `app`), mover su contenido a la raíz sin sobrescribir `.specify/`, `specs/`, `.claude/` ni `.gitignore`, y borrar `_tmp_app/`. Fusionar en el `.gitignore` existente las entradas del `.gitignore` de Laravel que falten (conservar todas las actuales, incluida `!.env.example`).
- [ ] T002 Crear `docker/php/Dockerfile` basado en `php:8.3-fpm` con extensiones `pdo_mysql`, `bcmath`, `intl`, `zip`, `pcntl`, Composer 2 copiado desde `composer:2`, `WORKDIR /var/www/html` y usuario no root con UID/GID configurables por `ARG`.
- [ ] T003 [P] Crear `docker/nginx/default.conf` sirviendo `/var/www/html/public`, `try_files $uri $uri/ /index.php?$query_string;` y `fastcgi_pass app:9000`.
- [ ] T004 [P] Crear `docker/mysql/init/01-create-testing-db.sql` que cree la base `sge_testing` (`utf8mb4`, `utf8mb4_unicode_ci`) y otorgue todos sus privilegios al usuario `${DB_USERNAME}` definido en `.env` (usar el nombre de usuario literal `sge`, igual que en `.env.example`).
- [ ] T005 Crear `docker-compose.yml` con servicios: `app` (build `docker/php/Dockerfile`, monta `.:/var/www/html`), `web` (`nginx:alpine`, puerto `8080:80`, monta `docker/nginx/default.conf`), `db` (`mysql:8.0`, `MYSQL_DATABASE=sge`, `MYSQL_USER=sge`, volumen nombrado `db_data`, monta `docker/mysql/init/` en `/docker-entrypoint-initdb.d`, `--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`, healthcheck), `node` (`node:20-alpine`, `profiles: [dev]`, monta `.:/app`, `working_dir: /app`) y `k6` (`grafana/k6`, `profiles: [load]`, monta `tests/Load:/scripts`, red compartida para llegar a `http://web`).
- [ ] T006 Instalar dependencias fijadas (research R-14): `composer require livewire/livewire:^4.2` y `composer require --dev pestphp/pest:^4.4 pestphp/pest-plugin-laravel:^4.0 pestphp/pest-plugin-livewire:^4.0 --with-all-dependencies`; ejecutar `./vendor/bin/pest --init`; borrar `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`.
- [ ] T007 [P] Publicar `config/livewire.php` (`php artisan livewire:publish --config`) y fijar `'make_command' => ['type' => 'class']` y `'component_layout' => 'layouts::app'` (contracts/componentes-livewire.md, "Convenciones Livewire 4").
- [ ] T008 [P] Verificar que el esqueleto trae Tailwind CSS vía Vite (`vite.config.js`, `resources/css/app.css`, `package.json`); si no, instalar `tailwindcss` y `@tailwindcss/vite` y configurarlos. `resources/css/app.css` debe incluir las fuentes de `resources/views/**/*.blade.php` y `app/Livewire/**/*.php`.
- [ ] T009 Actualizar `.env.example` con: `APP_NAME=SGE`, `APP_LOCALE=es`, `APP_URL=http://localhost:8080`, `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=sge`, `DB_USERNAME=sge`, `DB_PASSWORD=secret`, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SGE_ADMIN_USERNAME=admin`, `SGE_ADMIN_EMAIL=admin@example.com`, `SGE_ADMIN_PASSWORD=` (vacío, con comentario que indique que es obligatorio). Copiar a `.env` local.
- [ ] T010 [P] Configurar `phpunit.xml` para pruebas sobre MySQL: `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_DATABASE=sge_testing`, `SESSION_DRIVER=array`, `SGE_ADMIN_PASSWORD=password-de-prueba` (research R-11).
- [ ] T011 [P] Crear `config/sge.php` que devuelva `['admin' => ['username' => env('SGE_ADMIN_USERNAME'), 'email' => env('SGE_ADMIN_EMAIL'), 'password' => env('SGE_ADMIN_PASSWORD')]]` (research R-10).
- [ ] T012 [P] Crear `lang/es/validation.php` con mensajes en español para las reglas usadas en el módulo: `required`, `string`, `max.string`, `min.string`, `email`, `unique`, `exists`, `regex`, `confirmed`, `array`, y un bloque `attributes` que traduzca `login`, `password`, `nombres`, `apellidos`, `username`, `email`, `rol_id`, `identificador`, `nombre`, `descripcion`.

**Checkpoint**: `docker compose up -d --build` levanta `app`, `web` y `db`; `http://localhost:8080` muestra la página por defecto de Laravel y `docker compose exec app ./vendor/bin/pest` ejecuta 0 pruebas sin errores.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema de datos, modelos, factories, layout base, helpers de prueba y la regla de
"cuenta administradora", que usan todas las historias.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T013 Editar `database/migrations/0001_01_01_000000_create_users_table.php` para que cree **solo** la tabla `sessions` (eliminar `users` y `password_reset_tokens`; data-model.md "Tablas estándar"). El índice `sessions.user_id` se conserva como `foreignId('user_id')->nullable()->index()` sin restricción FK.
- [ ] T014 [P] Crear `database/migrations/2026_09_27_000001_create_roles_table.php`: tabla `roles` con `id`, `nombre` varchar(50) "obligatorio, único", `descripcion` varchar(255) "opcional", timestamps.
- [ ] T015 [P] Crear `database/migrations/2026_09_27_000002_create_permisos_table.php`: tabla `permisos` con `id`, `identificador` varchar(100) "obligatorio, único, inmutable", `nombre` varchar(100) "obligatorio", `descripcion` varchar(255) "opcional", timestamps.
- [ ] T016 Crear `database/migrations/2026_09_27_000003_create_rol_permiso_table.php`: tabla `rol_permiso` con `rol_id` FK → `roles.id` `ON DELETE CASCADE`, `permiso_id` FK → `permisos.id` `ON DELETE CASCADE`, PK compuesta (`rol_id`, `permiso_id`), timestamps (depende de T014, T015).
- [ ] T017 Crear `database/migrations/2026_09_27_000004_create_usuarios_table.php`: tabla `usuarios` con `id`, `nombres` varchar(100) obligatorio, `apellidos` varchar(100) obligatorio, `username` varchar(50) único, `email` varchar(150) nullable único, `password` varchar(255), `rol_id` FK → `roles.id` `ON DELETE RESTRICT` obligatorio, `activo` boolean default `true`, `rememberToken()`, timestamps (depende de T014).
- [ ] T018 [P] Crear `app/Models/Rol.php` con `protected $table = 'roles'`, `$fillable = ['nombre', 'descripcion']`, relaciones `usuarios()` hasMany `Usuario` (FK `rol_id`) y `permisos()` belongsToMany `Permiso` tabla `rol_permiso` (`rol_id`, `permiso_id`) `->withTimestamps()`.
- [ ] T019 [P] Crear `app/Models/Permiso.php` con `$fillable = ['identificador', 'nombre', 'descripcion']`, relación `roles()` belongsToMany `Rol` tabla `rol_permiso`, y un evento `updating` que lance `LogicException('El identificador de un permiso es inmutable.')` si `identificador` cambió (data-model.md: "Solo se fija al crear").
- [ ] T020 Crear `app/Models/Usuario.php` (extiende `Illuminate\Foundation\Auth\User`) con `$fillable = ['nombres', 'apellidos', 'username', 'email', 'password', 'rol_id', 'activo']`, `$hidden = ['password', 'remember_token']`, casts `password => 'hashed'` y `activo => 'boolean'`, mutators que guarden `username` y `email` en minúsculas (`email` vacío → `null`), relación `rol()` belongsTo `Rol` y accesor `nombre_completo`. Borrar `app/Models/User.php` (depende de T018).
- [ ] T021 Actualizar `config/auth.php`: provider `users` → `'model' => App\Models\Usuario::class`; eliminar la sección `passwords` o dejarla sin uso documentado (no hay recuperación de contraseña).
- [ ] T022 [P] Crear `database/factories/RolFactory.php` (nombre único con `fake()->unique()->jobTitle()`), `database/factories/PermisoFactory.php` (`identificador` con formato `recurso.accion` único, p. ej. `'prueba.' . fake()->unique()->lexify('??????')`) y `database/factories/UsuarioFactory.php` (username único `^[a-z0-9._-]{3,50}$`, email único, password `'password'`, `rol_id` → `Rol::factory()`, `activo => true`, estado `inactivo()`). Borrar `database/factories/UserFactory.php`.
- [ ] T023 [P] Crear `resources/views/layouts/app.blade.php` (layout autenticado: `@vite`, `@livewireStyles`/`@livewireScripts` si hace falta, cabecera con nombre del usuario y botón "Cerrar sesión" que envía `POST /logout` con `@csrf`, contenedor `{{ $slot ?? '' }}` / `@yield('content')`, y un `@include('layouts.partials.menu')` que en esta fase es un archivo vacío `resources/views/layouts/partials/menu.blade.php`) y `resources/views/layouts/guest.blade.php` (layout sin menú para el login).
- [ ] T024 Configurar `tests/Pest.php`: `pest()->extend(Tests\TestCase::class)->use(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature')` (toda prueba que toca la base de datos es Feature) y `pest()->extend(Tests\TestCase::class)->in('Unit')` **sin** `RefreshDatabase` (las Unit pueden usar el contenedor y `config()`, pero nunca la BD); helper global `usuarioConPermisos(array $identificadores = [], array $atributos = []): App\Models\Usuario` que cree un rol de fábrica, cree/obtenga los permisos por identificador, los asigne al rol y devuelva un usuario activo de ese rol (depende de T018–T022).
- [ ] T025 [P] Escribir `tests/Feature/Models/UsuarioTest.php`: `username` y `email` se guardan en minúsculas; email vacío se guarda como `null`; la contraseña se guarda con hash y `Hash::check` funciona; un usuario sin `rol_id` no se puede insertar (excepción de BD).
- [ ] T026 [P] Escribir `tests/Feature/Models/PermisoTest.php`: crear un permiso funciona; cambiar `nombre` o `descripcion` funciona; cambiar `identificador` lanza `LogicException`; un par rol–permiso duplicado en `rol_permiso` lanza excepción de BD.
- [ ] T027 Escribir `tests/Feature/Autorizacion/ProteccionAdministracionTest.php`: cubre las tres situaciones de la tabla "Regla derivada: cuenta administradora" de data-model.md — desactivar a la única cuenta administradora → rechazado; cambiar su rol a uno sin los permisos → rechazado; quitar `usuarios.editar` o `roles.asignar_permisos` al rol de la única cuenta administradora → rechazado; con dos cuentas administradoras activas las tres operaciones se permiten; una cuenta inactiva no cuenta como administradora.
- [ ] T028 Implementar `app/Autorizacion/ProteccionAdministracion.php` (research R-08): constante `PERMISOS_ADMINISTRACION = ['usuarios.editar', 'roles.asignar_permisos']`; método `contarCuentasAdministradoras(?int $excluirUsuarioId = null, ?array $simularPermisosRol = null): int` que cuente usuarios `activo = true` cuyo rol tenga ambos permisos (con `whereHas` sobre `rol.permisos`); y métodos públicos `puedeDesactivar(Usuario $objetivo): bool`, `puedeCambiarRol(Usuario $objetivo, Rol $nuevoRol): bool`, `puedeSincronizarPermisos(Rol $rol, array $permisoIds): bool`. Sin referencias a nombres de rol. Hacer pasar T027.

**Checkpoint**: `php artisan migrate:fresh` crea `roles`, `permisos`, `rol_permiso`, `usuarios`, `sessions`; T025–T027 en verde.

---

## Phase 3: User Story 1 - Iniciar y cerrar sesión (Priority: P1) 🎯 MVP

**Goal**: Cualquier usuario activo entra con correo o nombre de usuario + contraseña y puede salir;
credenciales inválidas, cuentas inactivas y ráfagas de intentos se rechazan (FR-001…FR-008).

**Independent Test**: Con usuarios creados por factory: login con correo, con username, con
contraseña incorrecta, con usuario inexistente, con cuenta inactiva; logout; acceso sin sesión a
`/inicio` redirige a `/login`.

### Tests for User Story 1 ⚠️

> Escribir primero; deben FALLAR antes de implementar.

- [ ] T029 [P] [US1] Escribir `tests/Feature/Auth/LoginTest.php` según la tabla "Resultados de `POST /login`" de contracts/rutas-web.md: `GET /login` responde 200; login con email → 302 a `/inicio` y autenticado; login con username → igual; login con `USERNAME` en mayúsculas → funciona; contraseña incorrecta → 302 a `/login`, error en `login` exactamente "Las credenciales no son válidas.", no autenticado; usuario inexistente → mismo mensaje; campos vacíos → errores de validación en `login` y `password`; la sesión se regenera tras login exitoso; usuario autenticado que visita `/login` → 302 a `/inicio`.
- [ ] T030 [P] [US1] Escribir `tests/Feature/Auth/CuentaInactivaTest.php`: login con credenciales válidas de una cuenta `activo=false` → 302 a `/login`, error "Tu cuenta está inactiva. Contacta al administrador.", no autenticado; un usuario autenticado cuya cuenta se marca `activo=false` en BD es deslogueado en su siguiente `GET /inicio`, redirigido a `/login` con el mismo mensaje y la sesión queda invalidada.
- [ ] T031 [P] [US1] Escribir `tests/Feature/Auth/LimiteIntentosTest.php`: 5 intentos fallidos con el mismo `login` desde la misma IP → el 6.º responde con error que empieza por "Demasiados intentos." aunque la contraseña sea correcta; tras `travel(61)->seconds()` se puede volver a iniciar sesión; un login exitoso limpia el contador; los intentos con otro `login` no se ven afectados.
- [ ] T032 [P] [US1] Escribir `tests/Feature/Auth/LogoutTest.php`: `POST /logout` autenticado → 302 a `/login`, sesión invalidada; después `GET /inicio` → 302 a `/login`; `GET /inicio` sin sesión → 302 a `/login`; `GET /` autenticado → 302 a `/inicio`; `/inicio` responde 200 a un usuario sin ningún permiso (FR edge case "Rol sin permisos").

### Implementation for User Story 1

- [ ] T033 [P] [US1] Crear `app/Http/Requests/Auth/LoginRequest.php` (research R-02, R-03, R-04): reglas `login` required|string|max:150 y `password` required|string; método `authenticate(): void` que: (1) calcula la clave `login|{mb_strtolower(login)}|{ip}` y si `RateLimiter::tooManyAttempts($clave, 5)` lanza `ValidationException` en `login` con "Demasiados intentos. Intenta nuevamente en :seconds segundos."; (2) usa la columna `email` si `login` contiene `@`, si no `username`, con el valor en minúsculas; (3) si `Auth::attempt` falla → `RateLimiter::hit($clave, 60)` y `ValidationException` en `login` con "Las credenciales no son válidas."; (4) si el usuario autenticado tiene `activo=false` → `Auth::logout()` y `ValidationException` "Tu cuenta está inactiva. Contacta al administrador."; (5) si tiene éxito → `RateLimiter::clear($clave)`.
- [ ] T034 [US1] Crear `app/Http/Controllers/Auth/LoginController.php` con `create()` (vista `auth.login`), `store(LoginRequest $request)` (`authenticate()`, `session()->regenerate()`, `redirect()->intended(route('inicio'))`) y `destroy(Request $request)` (`Auth::logout()`, `invalidate()`, `regenerateToken()`, redirect a `route('login')`) (depende de T033).
- [ ] T035 [P] [US1] Crear `app/Http/Middleware/EnsureUserIsActive.php`: si hay usuario autenticado con `activo=false` → `Auth::logout()`, invalidar sesión, regenerar token y `redirect()->route('login')->withErrors(['login' => 'Tu cuenta está inactiva. Contacta al administrador.'])`.
- [ ] T036 [US1] En `bootstrap/app.php` registrar el alias de middleware `'activo' => EnsureUserIsActive::class`, `redirectGuestsTo(fn () => route('login'))` y `redirectUsersTo(fn () => route('inicio'))` (depende de T035).
- [ ] T037 [US1] Definir en `routes/web.php`: grupo `guest` con `GET /login` → `LoginController@create` (`login`) y `POST /login` → `LoginController@store` (`login.store`); grupo `['auth', 'activo']` con `POST /logout` (`logout`), `GET /` → redirect a `/inicio` y `GET /inicio` → vista `inicio` (`inicio`). Eliminar la ruta `welcome` y `resources/views/welcome.blade.php` (depende de T034, T036).
- [ ] T038 [P] [US1] Crear `resources/views/auth/login.blade.php` (usa `layouts.guest`): formulario `POST {{ route('login.store') }}` con `@csrf`, campo `login` (etiqueta "Correo o nombre de usuario", `autofocus`, conserva `old('login')`), campo `password`, botón "Ingresar" y bloque que muestra `$errors->first('login')`.
- [ ] T039 [P] [US1] Crear `resources/views/inicio.blade.php` (usa `layouts.app`): saludo "Bienvenido, {{ nombre_completo }}" y texto placeholder para módulos futuros.
- [ ] T040 [US1] Ejecutar `./vendor/bin/pest tests/Feature/Auth` y corregir hasta que T029–T032 pasen.

**Checkpoint**: Login/logout funcional e independiente; US1 entregable como MVP.

---

## Phase 4: User Story 2 - Autorización por permisos del rol (Priority: P1)

**Goal**: Toda decisión de acceso se resuelve contra `rol_permiso` en cada solicitud; menú filtrado
por permisos; acciones sin permiso → 403 (FR-009…FR-015).

**Independent Test**: Con permisos creados en el test, un usuario con `usuarios.ver` ve el ítem
"Usuarios" y accede a una ruta de prueba protegida con `can:usuarios.ver`; sin el permiso no ve el
ítem y recibe 403; quitar/otorgar el permiso surte efecto en la siguiente solicitud.

### Tests for User Story 2 ⚠️

- [ ] T041 [P] [US2] Escribir `tests/Feature/Models/UsuarioTienePermisoTest.php`: `tienePermiso` es verdadero para un permiso del rol y falso para uno ajeno o inexistente; tras la primera llamada no ejecuta más consultas en la misma instancia (`DB::enableQueryLog`); una instancia nueva (`fresh()`) refleja cambios en `rol_permiso`.
- [ ] T042 [P] [US2] Escribir `tests/Feature/Autorizacion/GateTest.php`: registrar en el test una ruta `Route::middleware(['web', 'auth', 'activo', 'can:usuarios.ver'])->get('/_prueba-permiso', fn () => 'ok')`; usuario con `usuarios.ver` → 200; sin él → 403; `Gate::allows('permiso.inexistente')` → false; tras quitar el permiso al rol (`detach`) la siguiente solicitud del mismo usuario → 403 sin volver a iniciar sesión; tras otorgarlo (`attach`) → 200; cambiar el `rol_id` del usuario a un rol sin el permiso → 403 en la siguiente solicitud.
- [ ] T043 [P] [US2] Escribir `tests/Feature/Autorizacion/MenuTest.php` según la tabla "Menú" de contracts/rutas-web.md: `GET /inicio` con un usuario con `usuarios.ver` y `roles.ver` muestra los enlaces "Usuarios", "Roles" y "Permisos"; con un usuario sin permisos solo muestra "Inicio" y no contiene los textos "Usuarios", "Roles" ni "Permisos" en el menú; con solo `usuarios.ver` muestra "Usuarios" pero no "Roles". (Las rutas `admin.*` pueden no existir aún: el menú debe ocultar ítems cuya ruta no esté registrada con `Route::has`.)
- [ ] T044 [P] [US2] Escribir `tests/Feature/Autorizacion/SinCondicionalesDeRolTest.php` (SC-005): recorrer los archivos de `app/`, `routes/`, `config/` y `resources/views/` y fallar si alguno contiene (sin distinguir mayúsculas) los literales `'Administrador'`, `'Director'`, `'Docente'`, `'Padre de Familia'`, `'Alumno'` (entre comillas simples o dobles) o expresiones como `->rol->nombre ==` / `rol->nombre ===`; `database/seeders/` queda excluido.
- [ ] T045 [P] [US2] Escribir `tests/Feature/Autorizacion/Pagina403Test.php`: una respuesta 403 muestra la vista en español con el texto "No tienes permiso para realizar esta acción." y un enlace a `/inicio`.

### Implementation for User Story 2

- [ ] T046 [US2] Añadir a `app/Models/Usuario.php` el método `tienePermiso(string $identificador): bool` con memoización en una propiedad protegida `?array $identificadoresPermiso = null` que se llena una sola vez con `$this->rol->permisos()->pluck('identificador')->all()` (research R-05). No cachear entre solicitudes.
- [ ] T047 [US2] En `app/Providers/AppServiceProvider.php::boot()` registrar `Gate::before(fn (Usuario $usuario, string $ability) => $usuario->tienePermiso($ability) ? true : null);` con un comentario que explique que devolver `null` hace que Laravel deniegue las habilidades no definidas (research R-05) (depende de T046).
- [ ] T048 [P] [US2] Crear `config/menu.php` con los ítems de la tabla "Menú" de contracts/rutas-web.md: `['etiqueta' => 'Inicio', 'ruta' => 'inicio', 'permiso' => null]`, `Usuarios` → `admin.usuarios.index` / `usuarios.ver`, `Roles` → `admin.roles.index` / `roles.ver`, `Permisos` → `admin.permisos.index` / `roles.ver`.
- [ ] T049 [US2] Implementar `resources/views/layouts/partials/menu.blade.php`: recorrer `config('menu')`, omitir ítems cuya ruta no exista (`Route::has`), mostrar los de `permiso` nulo y los demás solo dentro de `@can($item['permiso'])`; marcar el ítem activo con `request()->routeIs($item['ruta'].'*')` (depende de T048).
- [ ] T050 [P] [US2] Crear `resources/views/errors/403.blade.php` en español: título "Acceso denegado", texto "No tienes permiso para realizar esta acción." y enlace "Volver al inicio" a `/inicio`.
- [ ] T051 [US2] Ejecutar `./vendor/bin/pest tests/Feature/Models/UsuarioTienePermisoTest.php tests/Feature/Autorizacion` y corregir hasta que T041–T045 pasen.

**Checkpoint**: RBAC por tabla operativo para cualquier ruta o acción futura; US1 sigue en verde.

---

## Phase 5: User Story 5 - Datos iniciales de roles y permisos (Priority: P1)

**Goal**: Una instalación nueva queda operativa con 5 roles, 7 permisos base, sus asignaciones y
un Administrador inicial desde el entorno (FR-023, FR-024, SC-008).

**Independent Test**: `migrate:fresh --seed` dos veces seguidas: sin duplicados, 5 roles, 7
permisos, asignaciones de research R-07, y el Administrador inicial inicia sesión y tiene
`usuarios.editar` y `roles.asignar_permisos`.

### Tests for User Story 5 ⚠️

- [ ] T052 [P] [US5] Escribir `tests/Feature/Seeders/RolesYPermisosSeederTest.php`: tras `seed(RolesYPermisosSeeder::class)` existen exactamente los roles "Administrador", "Director", "Docente", "Padre de Familia", "Alumno" y los 7 identificadores `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.desactivar`, `roles.ver`, `permisos.gestionar`, `roles.asignar_permisos`; Administrador tiene los 7; Director tiene solo `usuarios.ver` y `roles.ver`; Docente, Padre de Familia y Alumno no tienen ninguno; ejecutar el seeder dos veces no duplica filas; si tras la primera ejecución se quita `usuarios.ver` al Director, una segunda ejecución NO lo vuelve a asignar.
- [ ] T053 [P] [US5] Escribir `tests/Feature/Seeders/AdministradorInicialSeederTest.php`: con `config(['sge.admin' => [...]])` crea un usuario activo con ese username/email y rol Administrador; puede hacer `POST /login` con esa contraseña y `Gate::forUser($admin)->allows('roles.asignar_permisos')` es verdadero; si el usuario ya existe con otra contraseña, re-ejecutar el seeder no la cambia; si `sge.admin.password` está vacío lanza `RuntimeException` con mensaje que menciona `SGE_ADMIN_PASSWORD`.
- [ ] T054 [P] [US5] Escribir `tests/Feature/Seeders/DatabaseSeederTest.php`: `seed()` completo deja 5 roles, 7 permisos y el Administrador inicial; con `app()->detectEnvironment(fn () => 'testing')` no crea los usuarios demo.

### Implementation for User Story 5

- [ ] T055 [P] [US5] Crear `database/seeders/RolesYPermisosSeeder.php` con dos arreglos constantes: los 5 roles (nombre + descripción) y los 7 permisos base (identificador, nombre visible y roles que lo reciben, según la tabla de research R-07). Usar `Rol::firstOrCreate(['nombre' => ...])` y `Permiso::firstOrCreate(['identificador' => ...], [...])`; asignar el permiso a sus roles **solo si** `$permiso->wasRecentlyCreated` (data-model.md "Nota sobre idempotencia").
- [ ] T056 [P] [US5] Crear `database/seeders/AdministradorInicialSeeder.php`: leer `config('sge.admin')`; si `password` está vacío lanzar `RuntimeException('Define SGE_ADMIN_PASSWORD en .env antes de ejecutar los seeders.')`; `Usuario::firstOrCreate(['username' => ...], ['nombres' => 'Administrador', 'apellidos' => 'del Sistema', 'email' => ..., 'password' => ..., 'rol_id' => Rol::where('nombre', 'Administrador')->value('id'), 'activo' => true])` (el nombre de rol se permite aquí porque es un seeder).
- [ ] T057 [P] [US5] Crear `database/seeders/UsuariosDemoSeeder.php`: un usuario activo por rol (`director.demo`, `docente.demo`, `padre.demo`, `alumno.demo`, contraseña `password`), idempotente con `firstOrCreate`.
- [ ] T058 [US5] Actualizar `database/seeders/DatabaseSeeder.php` para llamar a `RolesYPermisosSeeder` y `AdministradorInicialSeeder`, y a `UsuariosDemoSeeder` solo si `app()->environment('local')` (depende de T055–T057).
- [ ] T059 [US5] Ejecutar `./vendor/bin/pest tests/Feature/Seeders` y `php artisan migrate:fresh --seed && php artisan db:seed` en el contenedor; corregir hasta que T052–T054 pasen y la segunda siembra no falle.

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

- [ ] T060 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/IndexTest.php`: `GET /admin/usuarios` con `usuarios.ver` → 200 y lista usuarios con nombre completo, username, email, rol y estado; sin permiso → 403; `Livewire::test(Index::class)->set('q', 'texto')` filtra por nombres, apellidos, username o email; pagina de 15 en 15.
- [ ] T061 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/CrearUsuarioTest.php`: `GET /admin/usuarios/crear` con `usuarios.crear` → 200, sin él → 403; `Livewire::test(Form::class)->set(...)->call('guardar')` crea un usuario activo con email opcional; username/email se guardan en minúsculas; errores de validación para: username duplicado (sin distinguir mayúsculas), email duplicado, username con `@` o fuera de `^[a-z0-9._-]{3,50}$`, password < 8 o sin confirmar, `rol_id` inexistente, nombres/apellidos vacíos o > 100; sin `usuarios.crear` `call('guardar')` → `assertForbidden()` y no se crea nada; el usuario creado puede hacer `POST /login`.
- [ ] T062 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/EditarUsuarioTest.php`: `GET /admin/usuarios/{id}/editar` con `usuarios.editar` → 200, sin él → 403; editar datos respeta unicidad ignorando al propio usuario; cambiar `rol_id` hace que en la siguiente solicitud el usuario tenga los permisos del nuevo rol; cambiar el rol de la única cuenta administradora a un rol sin permisos de administración → error de validación en `rol_id` y el rol no cambia; `restablecerPassword` con `usuarios.editar` cambia la contraseña (la anterior ya no permite login, la nueva sí) y valida mínimo 8 + confirmación; sin `usuarios.editar`, `guardar` y `restablecerPassword` → 403.
- [ ] T063 [P] [US3] Escribir `tests/Feature/Admin/Usuarios/DesactivarUsuarioTest.php`: con `usuarios.desactivar`, `Livewire::test(Index::class)->call('desactivar', $id)` pone `activo=false` y ese usuario ya no puede iniciar sesión (mensaje de cuenta inactiva); `reactivar` lo vuelve a habilitar; desactivar la propia cuenta → error "No puedes desactivar tu propia cuenta" y sigue activa; desactivar la única cuenta administradora → error y sigue activa; sin `usuarios.desactivar` ambas acciones → 403.

### Implementation for User Story 3

- [ ] T064 [US3] Crear `app/Livewire/Admin/Usuarios/Index.php` (componente de clase, `WithPagination`) y `resources/views/livewire/admin/usuarios/index.blade.php`: `mount()` con `$this->authorize('usuarios.ver')`; propiedad `q` enlazada con `wire:model.live.debounce.300ms="q"`; consulta paginada de 15 con `rol` cargado; acciones `desactivar(int $id)` y `reactivar(int $id)` que llaman `$this->authorize('usuarios.desactivar')`, rechazan la propia cuenta y consultan `ProteccionAdministracion::puedeDesactivar()` (errores con `addError('general', ...)`); botones "Crear usuario", "Editar", "Desactivar"/"Reactivar" envueltos en `@can` con su permiso (contracts/componentes-livewire.md, `Admin\Usuarios\Index`).
- [ ] T065 [US3] Crear `app/Livewire/Admin/Usuarios/Form.php` y `resources/views/livewire/admin/usuarios/form.blade.php`: `mount(?Usuario $usuario = null)` autoriza `usuarios.crear` (nuevo) o `usuarios.editar` (existente); propiedades `nombres`, `apellidos`, `username`, `email`, `password`, `password_confirmation`, `rol_id`; `guardar()` vuelve a autorizar, normaliza `username`/`email` a minúsculas y valida: nombres/apellidos `required|string|max:100`; username `required|regex:/^[a-z0-9._-]{3,50}$/|unique:usuarios,username,{id}`; email `nullable|email|max:150|unique:usuarios,email,{id}`; password `required|min:8|confirmed` solo al crear; `rol_id` `required|exists:roles,id`; al editar con cambio de rol consulta `ProteccionAdministracion::puedeCambiarRol()` y, si falla, `addError('rol_id', 'No se puede cambiar el rol: el sistema quedaría sin cuentas administradoras.')`; tras guardar redirige a `admin.usuarios.index`. `restablecerPassword()` autoriza `usuarios.editar` y valida `password` `required|min:8|confirmed`. El selector de rol lista `Rol::orderBy('nombre')` (depende de T064 solo por la ruta de regreso).
- [ ] T066 [US3] Registrar en `routes/web.php`, dentro del grupo `['auth', 'activo']`: `Route::livewire('/admin/usuarios', Index::class)->name('admin.usuarios.index')->middleware('can:usuarios.ver')`, `Route::livewire('/admin/usuarios/crear', Form::class)->name('admin.usuarios.create')->middleware('can:usuarios.crear')` y `Route::livewire('/admin/usuarios/{usuario}/editar', Form::class)->name('admin.usuarios.edit')->middleware('can:usuarios.editar')` (depende de T064, T065).
- [ ] T067 [US3] Ejecutar `./vendor/bin/pest tests/Feature/Admin/Usuarios tests/Feature/Autorizacion/MenuTest.php` y corregir hasta que T060–T063 pasen y el ítem "Usuarios" aparezca en el menú.

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

- [ ] T068 [P] [US4] Escribir `tests/Feature/Admin/Permisos/IndexTest.php`: `GET /admin/permisos` con `roles.ver` → 200 y lista identificador, nombre y descripción; sin permiso → 403; con `permisos.gestionar`, `call('crear')` con identificador válido crea el permiso; errores para identificador duplicado, identificador que no cumple `^[a-z_]+(\.[a-z_]+)+$`, nombre vacío o > 100, descripción > 255; `call('actualizar', $id)` cambia nombre y descripción y el identificador queda igual (aunque se intente enviar otro); con solo `roles.ver`, `crear` y `actualizar` → 403.
- [ ] T069 [P] [US4] Escribir `tests/Feature/Admin/Roles/IndexTest.php`: `GET /admin/roles` con `roles.ver` → 200 y muestra cada rol con su cantidad de usuarios y de permisos; sin permiso → 403.
- [ ] T070 [P] [US4] Escribir `tests/Feature/Admin/Roles/PermisosDeRolTest.php`: `GET /admin/roles/{id}/permisos` con `roles.ver` → 200 con casillas marcadas según `rol_permiso`; sin permiso → 403; con `roles.asignar_permisos`, `set('seleccionados', [...])->call('guardar')` sincroniza `rol_permiso`; un usuario de ese rol recibe 403/200 en su siguiente solicitud según el cambio (sin reiniciar sesión); quitar `roles.asignar_permisos` o `usuarios.editar` del rol de la única cuenta administradora → error y `rol_permiso` sin cambios; ids inexistentes → error de validación; con solo `roles.ver`, `guardar` → 403.

### Implementation for User Story 4

- [ ] T071 [P] [US4] Crear `app/Livewire/Admin/Permisos/Index.php` y `resources/views/livewire/admin/permisos/index.blade.php`: `mount()` autoriza `roles.ver`; lista de permisos ordenada por identificador; formulario de creación (`identificador`, `nombre`, `descripcion`) y edición en línea (`nombre`, `descripcion`) visibles solo con `@can('permisos.gestionar')`; `crear()` autoriza `permisos.gestionar` y valida identificador `required|max:100|regex:/^[a-z_]+(\.[a-z_]+)+$/|unique:permisos,identificador`, nombre `required|string|max:100`, descripcion `nullable|string|max:255`; `actualizar(int $id)` autoriza `permisos.gestionar`, valida solo nombre y descripcion y hace `update(['nombre' => ..., 'descripcion' => ...])` (nunca el identificador).
- [ ] T072 [P] [US4] Crear `app/Livewire/Admin/Roles/Index.php` y `resources/views/livewire/admin/roles/index.blade.php`: `mount()` autoriza `roles.ver`; lista de roles con `withCount(['usuarios', 'permisos'])`, nombre, descripción y enlace "Permisos" a `admin.roles.permisos`.
- [ ] T073 [US4] Crear `app/Livewire/Admin/Roles/Permisos.php` y `resources/views/livewire/admin/roles/permisos.blade.php`: `mount(Rol $rol)` autoriza `roles.ver` y carga `seleccionados` con los ids actuales; casillas de todos los permisos (deshabilitadas sin `@can('roles.asignar_permisos')`); `guardar()` autoriza `roles.asignar_permisos`, valida `seleccionados` `array` y cada id `exists:permisos,id`, consulta `ProteccionAdministracion::puedeSincronizarPermisos($rol, $seleccionados)` y si falla `addError('seleccionados', 'No se puede guardar: el sistema quedaría sin cuentas administradoras.')`; si pasa, `$rol->permisos()->sync($seleccionados)` y mensaje de éxito.
- [ ] T074 [US4] Registrar en `routes/web.php`, dentro del grupo `['auth', 'activo']`: `Route::livewire('/admin/permisos', Permisos\Index::class)->name('admin.permisos.index')->middleware('can:roles.ver')`, `Route::livewire('/admin/roles', Roles\Index::class)->name('admin.roles.index')->middleware('can:roles.ver')` y `Route::livewire('/admin/roles/{rol}/permisos', Roles\Permisos::class)->name('admin.roles.permisos')->middleware('can:roles.ver')` (depende de T071–T073).
- [ ] T075 [US4] Ejecutar `./vendor/bin/pest tests/Feature/Admin/Permisos tests/Feature/Admin/Roles tests/Feature/Autorizacion/MenuTest.php` y corregir hasta que T068–T070 pasen y "Roles" y "Permisos" aparezcan en el menú.

**Checkpoint**: Política de acceso configurable desde la interfaz sin cambios de código.

---

## Phase 8: User Story 6 - Punto de extensión para filtros de pertenencia (Priority: P3)

**Goal**: Mecanismo reutilizable y fail-closed para restringir registros por relación con el
usuario, listo para los módulos 2–6 (FR-025…FR-028).

**Independent Test**: Con `RegistroDePrueba` + `ReglaDePrueba` (solo en `tests/`): con regla,
`visiblePara()` filtra y un registro ajeno da 403; sin regla, cualquier consulta da 403; un modelo
sin el trait no se altera.

### Tests for User Story 6 ⚠️

- [ ] T076 [P] [US6] Crear soporte de pruebas en `tests/Support/Pertenencia/`: `RegistroDePrueba.php` (modelo Eloquent con tabla `registros_de_prueba`: `id`, `usuario_id`, `titulo`; usa el trait `RequierePertenencia`), `ReglaDePrueba.php` (implementa `ReglaPertenencia`: `aplicar` filtra `where('usuario_id', $usuario->id)`, `permite` compara `usuario_id`) y `ModeloSinPertenencia.php` (modelo con tabla `modelos_sin_pertenencia`, sin el trait). Las tablas se crean con `Schema::create` en el `beforeEach` de las pruebas que las usan.
- [ ] T077 [P] [US6] Escribir `tests/Feature/Pertenencia/MecanismoPertenenciaTest.php` con los 4 casos de contracts/pertenencia.md "Pruebas que acompañan este contrato" más: con regla registrada (`config(['pertenencia.reglas' => [RegistroDePrueba::class => ReglaDePrueba::class]])`) un usuario sin registros propios obtiene colección vacía (no error); sin regla, `RegistroDePrueba::query()->visiblePara($u)->get()` lanza `ReglaPertenenciaFaltante` y, dentro de una ruta de prueba, la respuesta es 403; `autorizarPertenencia()` sin regla → 403.
- [ ] T078 [P] [US6] Escribir `tests/Unit/Autorizacion/RegistroReglasPertenenciaTest.php` (Unit: no toca la BD, solo `config()` y el contenedor): `reglaPara(RegistroDePrueba::class)` devuelve una instancia de `ReglaDePrueba` cuando está configurada y `null` cuando no; una clase configurada que no implementa `ReglaPertenencia` lanza `InvalidArgumentException`; `ReglaPertenenciaFaltante` es instancia de `Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException`.

### Implementation for User Story 6

- [ ] T079 [P] [US6] Crear `app/Autorizacion/Pertenencia/ReglaPertenencia.php` con la interfaz exacta de contracts/pertenencia.md (`aplicar(Builder $query, Usuario $usuario): Builder` y `permite(Usuario $usuario, Model $registro): bool`) y un docblock con las restricciones "MUST NOT comparar el nombre del rol".
- [ ] T080 [P] [US6] Crear `app/Autorizacion/Pertenencia/ReglaPertenenciaFaltante.php` extendiendo `AccessDeniedHttpException`, con constructor que recibe el nombre de la clase del modelo y usa el mensaje "No hay regla de pertenencia registrada para {Modelo}; acceso denegado por defecto.".
- [ ] T081 [P] [US6] Crear `config/pertenencia.php` que devuelva `['reglas' => []]` con un comentario de ejemplo `// App\Models\Alumno::class => App\Autorizacion\Pertenencia\Reglas\AlumnoRegla::class`.
- [ ] T082 [US6] Crear `app/Autorizacion/Pertenencia/RegistroReglasPertenencia.php`: método `reglaPara(string $modelo): ?ReglaPertenencia` que lee `config('pertenencia.reglas')`, instancia la clase con el contenedor y lanza `InvalidArgumentException` si no implementa la interfaz. Registrarlo como singleton en `app/Providers/AppServiceProvider.php::register()` (depende de T079).
- [ ] T083 [US6] Crear `app/Autorizacion/Pertenencia/RequierePertenencia.php` (trait): `scopeVisiblePara(Builder $query, Usuario $usuario): Builder` que obtiene la regla del registro y lanza `ReglaPertenenciaFaltante` si es `null`, o devuelve `$regla->aplicar($query, $usuario)`; y `autorizarPertenencia(Usuario $usuario): void` que lanza `ReglaPertenenciaFaltante` sin regla o `AccessDeniedHttpException` si `permite()` es falso (depende de T080, T082).
- [ ] T084 [US6] Ejecutar `./vendor/bin/pest tests/Feature/Pertenencia tests/Unit/Autorizacion` y corregir hasta que T076–T078 pasen.

**Checkpoint**: Punto de extensión probado; los módulos futuros solo añaden el trait y una regla.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Prueba de carga del login, verificación global de rutas protegidas, documentación y
validación final.

- [ ] T085 [P] Escribir `tests/Feature/Autorizacion/RutasProtegidasTest.php` (SC-002): recorrer `Route::getRoutes()` y, para cada ruta con middleware `can:*`, comprobar que un usuario activo sin permisos recibe 403 y un usuario con exactamente ese permiso no recibe 403 (sustituir parámetros `{usuario}`/`{rol}` por registros de factory).
- [ ] T086 [P] Crear `database/seeders/LoadTestSeeder.php`: 100 usuarios activos `carga001`…`carga100` con contraseña `password` y un rol sin permisos creado con `firstOrCreate(['nombre' => 'Carga'])`; idempotente; no se llama desde `DatabaseSeeder` (data-model.md, tabla de seeders).
- [ ] T087 [P] Crear `tests/Load/login.js` (research R-12): 100 VUs durante 1 min contra `http://web`; cada iteración hace `GET /login`, extrae `_token` del HTML, hace `POST /login` con `login=carga{NNN}` (según `__VU`) y `password=password` con la etiqueta `{ name: 'login' }`, verifica que la respuesta final sea `/inicio`, y hace `POST /logout`; `thresholds`: `'http_req_duration{name:login}': ['p(95)<2000']`, `http_req_failed: ['rate<0.01']`.
- [ ] T088 Ejecutar la prueba de carga (quickstart.md §4): `php artisan db:seed --class=LoadTestSeeder` y `docker compose --profile load run --rm k6 run /scripts/login.js`; guardar el resumen en `specs/001-auth-roles-rbac/evidencias/k6-login.txt`. Si algún umbral falla, registrar el resultado e investigar antes de dar el módulo por terminado (depende de T086, T087).
- [ ] T089 [P] Crear `README.md` en la raíz: requisitos, pasos de quickstart.md §1 (levantar, `.env`, `migrate:fresh --seed`, `npm run build`), cómo ejecutar pruebas y la prueba de carga, y la regla "los permisos se verifican por identificador, nunca por nombre de rol".
- [ ] T090 Ejecutar la suite completa `./vendor/bin/pest` y la verificación manual de quickstart.md §3 (10 pasos) y §5 (reconstrucción desde cero); corregir cualquier fallo.
- [ ] T091 Revisar el Definition of Done de la constitución para el módulo: todas las pruebas en verde, T044 en verde (sin nombres de rol en código), evidencia k6 guardada, `.env.example` actualizado y `docker compose up` sin pasos no documentados.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; BLOQUEA todas las historias.
- **US1 (Phase 3)**, **US2 (Phase 4)**, **US5 (Phase 5)**: dependen solo de Foundational.
- **US3 (Phase 6)** y **US4 (Phase 7)**: dependen de Foundational y de **US2** (necesitan
  `Gate::before` para que `authorize()` y `can:` consulten `rol_permiso`) y de **US1** (grupo de
  rutas `['auth', 'activo']` y layout con logout).
- **US6 (Phase 8)**: depende solo de Foundational (usa el modelo `Usuario`).
- **Polish (Phase 9)**: depende de todas las historias; T088 depende de T086–T087 y de US1.

### User Story Dependencies

```text
Setup ─▶ Foundational ─┬─▶ US1 ─┐
                       ├─▶ US2 ─┼─▶ US3
                       │        └─▶ US4
                       ├─▶ US5
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
- Tras Foundational: US1, US2, US5 y US6 pueden avanzar en paralelo; US3 y US4 en paralelo entre sí
  una vez listas US1 y US2.
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
Task: "T033 app/Http/Requests/Auth/LoginRequest.php"
Task: "T035 app/Http/Middleware/EnsureUserIsActive.php"
Task: "T038 resources/views/auth/login.blade.php"
Task: "T039 resources/views/inicio.blade.php"
```

## Parallel Example: User Story 4

```bash
Task: "T068 tests/Feature/Admin/Permisos/IndexTest.php"
Task: "T069 tests/Feature/Admin/Roles/IndexTest.php"
Task: "T070 tests/Feature/Admin/Roles/PermisosDeRolTest.php"
Task: "T071 app/Livewire/Admin/Permisos/Index.php"
Task: "T072 app/Livewire/Admin/Roles/Index.php"
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
- Verificar que las pruebas fallan antes de implementar
- Commit tras cada tarea o grupo lógico
- Nunca introducir comparaciones por nombre de rol; T044 lo verifica automáticamente
- Evitar: tareas vagas, conflictos en el mismo archivo (`routes/web.php` y `AppServiceProvider.php` se tocan en varias historias: no ejecutar esas tareas en paralelo)
