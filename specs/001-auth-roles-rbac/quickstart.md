# Quickstart: validar Autenticación y Roles

**Feature**: `001-auth-roles-rbac`

Guía para comprobar de punta a punta que el módulo funciona. Detalles de rutas y reglas en
[contracts/](contracts/) y [data-model.md](data-model.md).

## Prerrequisitos

- Docker Desktop con Docker Compose v2.
- `.env` creado a partir de `.env.example`, con al menos:
  - `DB_*` apuntando al servicio `db`
  - `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `SESSION_LIFETIME=120`
  - `SGE_ADMIN_USERNAME`, `SGE_ADMIN_EMAIL`, `SGE_ADMIN_PASSWORD`

## 1. Levantar y preparar

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
docker compose --profile dev run --rm node sh -c "npm install && npm run build"
```

Esperado: la aplicación responde en `http://localhost:8080/login`.

## 2. Pruebas automatizadas

```bash
docker compose exec app php artisan test
```

Esperado: todas en verde, incluyendo:

| Grupo | Cubre |
|---|---|
| `tests/Feature/Auth` | Login con correo y con usuario, credenciales inválidas, cuenta inactiva, rate limit, logout, redirecciones. |
| `tests/Feature/Autorizacion` | Permitido/denegado (403) por cada ruta y acción Livewire; cambios de permisos efectivos en la siguiente solicitud; menú por rol. |
| `tests/Feature/Admin` | CRUD de usuarios, restablecer contraseña, desactivar/reactivar, protección de la última cuenta administradora, permisos y asignación a roles. |
| `tests/Feature/Seeders` | 5 roles, 7 permisos, asignaciones, Administrador inicial, idempotencia. |
| `tests/Feature/Pertenencia` | Filtro con regla de prueba, 403 sin regla (fail-closed), modelo sin trait. |
| `tests/Feature/Models` | Normalización de `Usuario`, inmutabilidad del identificador de `Permiso`, `tienePermiso` contra la BD. |
| `tests/Feature/Autorizacion/ProteccionAdministracionTest.php` | Regla de la última cuenta administradora. |
| `tests/Unit` (sin BD) | Detección correo/username y clave de limitación del login, duración de sesión (120 min), `tienePermiso` en memoria (incluida cuenta inactiva), expresiones regulares de username e identificador de permiso, definición de roles/permisos del seeder, `RegistroReglasPertenencia`. |

## 3. Verificación manual

| # | Paso | Resultado esperado |
|---|---|---|
| 1 | Entrar con `SGE_ADMIN_USERNAME` y luego (tras salir) con `SGE_ADMIN_EMAIL` | Ambos acceden a `/inicio`; menú: Inicio, Usuarios, Roles, Permisos. |
| 2 | Entrar con una contraseña incorrecta | "Las credenciales no son válidas." |
| 3 | Como Admin, crear usuario `docente1` con rol Docente | Aparece activo en la lista. |
| 4 | Entrar como `docente1` | Menú solo con Inicio; abrir `/admin/usuarios` → 403. |
| 5 | Como Admin, dar `usuarios.ver` al rol Docente; recargar la sesión de `docente1` | Aparece "Usuarios" sin volver a iniciar sesión. Quitarlo de nuevo. |
| 6 | Como Admin, desactivar `docente1` mientras tiene sesión abierta | Su siguiente clic lo lleva a `/login` con "Tu cuenta está inactiva". No puede volver a entrar. |
| 7 | Con `docente1` autenticado en una pestaña, abrir una pantalla de administración (por ejemplo `/admin/usuarios`, si su rol tiene el permiso) y dejarla abierta sin recargar; desde otra sesión (otro navegador o ventana privada) como Admin, desactivar la cuenta `docente1`; sin recargar la primera pestaña, ejecutar ahí una acción del componente Livewire (p. ej. el buscador o un botón) | La acción responde 403 y no se ejecuta; el middleware `activo` está registrado como middleware persistente de Livewire, así que la desactivación surte efecto sin necesidad de recargar la página. |
| 8 | Como Admin, intentar desactivar la propia cuenta | Error "No puedes desactivar tu propia cuenta". |
| 9 | Como Admin, quitar `roles.asignar_permisos` al rol Administrador (siendo el único admin) | Error: dejaría el sistema sin cuentas administradoras. |
| 10 | Crear un permiso `reportes.ver` y editar su nombre | Se guarda; el identificador no es editable. |
| 11 | 6 intentos fallidos seguidos con el mismo usuario | El 6.º muestra "Demasiados intentos…". |

## 4. Prueba de carga (SC-006)

El script `tests/Load/carga.js` lee `BASE_URL` (por defecto `http://web`, la red de Compose) y tiene
dos modalidades. Antes, sembrar los 1000 usuarios `carga0001`…`carga1000` (falla en producción):

```bash
docker compose exec app php artisan db:seed --class=LoadTestSeeder
```

**Prueba de humo (local, 10 VUs, ~35 s)** — valida que el script y el login funcionan:

```bash
docker compose --profile load run --rm k6 run -e SMOKE=1 /scripts/carga.js
# contra otro servidor:
docker compose --profile load run --rm k6 run -e SMOKE=1 -e BASE_URL=https://<fqdn-del-servidor> /scripts/carga.js
```

**Rampa completa (1000 VUs, ~15 min)** — solo contra el servidor desplegado en pruebas
(`APP_ENV=staging`, nunca producción), no en un equipo local; el procedimiento completo, con su lista de verificación, está en [`docs/despliegue/prueba-de-carga.md`](../../docs/despliegue/prueba-de-carga.md):

```bash
docker compose --profile load run --rm k6 run -e BASE_URL=https://<fqdn-del-servidor> /scripts/carga.js
```

Esperado (rampa 100 → 300 → 600 → 1000 VUs): `p(95) < 3500 ms` en el login,
`p(95) < 1500 ms` en las consultas y `http_req_failed < 1 %`. En staging, guardar la evidencia de cada sesión como
`specs/001-auth-roles-rbac/evidencias/k6-sesion-<fecha>.md` (plantilla `k6-sesion-plantilla.md`, con el
resumen de k6, el CSV de CPU/memoria y `cierre-carga.json`; solo vale si `evidencia_completa: true`). La prueba de
humo local queda en `evidencias/k6-humo.txt`.

## 5. Reconstrucción desde cero (SC-008)

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed   # segunda vez: sin duplicados ni errores
```

Esperado: el Administrador inicial puede entrar y existen exactamente 5 roles y 7 permisos.
