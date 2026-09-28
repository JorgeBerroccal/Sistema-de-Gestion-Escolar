# Data Model: Autenticación y Roles (RBAC)

**Feature**: `001-auth-roles-rbac` | **Date**: 2026-09-27

Motor: MySQL 8, `utf8mb4` / `utf8mb4_unicode_ci` (comparaciones de texto insensibles a mayúsculas).
Todas las tablas tienen `created_at` y `updated_at`.

```text
roles 1 ──< usuarios
roles >──< permisos   (vía rol_permiso)
```

## roles (modelo `Rol`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombre | varchar(50) | obligatorio, único |
| descripcion | varchar(255) | opcional |

- Los 5 registros iniciales (Administrador, Director, Docente, Padre de Familia, Alumno) los crea
  `RolesYPermisosSeeder`. No hay CRUD de roles en la interfaz en este módulo.
- El código **nunca** consulta un rol por su nombre para decidir acceso (Principio III). El nombre
  solo se usa en seeders y para mostrarse en pantalla.
- Modelo `Rol` declara `$table = 'roles'` (el pluralizador de Laravel generaría `rols`).

## permisos (modelo `Permiso`)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| identificador | varchar(100) | obligatorio, único, **inmutable**, formato `^[a-z_]+(\.[a-z_]+)+$` |
| nombre | varchar(100) | obligatorio |
| descripcion | varchar(255) | opcional |

- `identificador` es lo que el código referencia (`can('usuarios.crear')`). Solo se fija al crear:
  el formulario de edición no lo expone y el modelo `Permiso` registra un evento `updating` que lanza
  `LogicException('El identificador de un permiso es inmutable.')` si `identificador` cambia
  (sigue en `$fillable` para poder crearlo). Formato validado con `Permiso::REGEX_IDENTIFICADOR`.
- No se eliminan permisos desde la interfaz.

## rol_permiso (tabla pivote)

| Campo | Tipo | Reglas |
|---|---|---|
| rol_id | FK → roles.id | `ON DELETE CASCADE` |
| permiso_id | FK → permisos.id | `ON DELETE CASCADE` |

- PK compuesta (`rol_id`, `permiso_id`): un par aparece como máximo una vez.
- Se modifica con `$rol->permisos()->sync([...])`.

## usuarios (modelo `Usuario`, autenticable)

| Campo | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| nombres | varchar(100) | obligatorio |
| apellidos | varchar(100) | obligatorio |
| username | varchar(50) | obligatorio, único, minúsculas, `^[a-z0-9._-]{3,50}$` (sin `@`) |
| email | varchar(150) | opcional, único cuando existe, minúsculas, formato correo |
| password | varchar(255) | hash bcrypt; mínimo 8 caracteres en texto plano al asignarla |
| rol_id | FK → roles.id | obligatorio, `ON DELETE RESTRICT` |
| activo | boolean | obligatorio, por defecto `true` |
| remember_token | varchar(100) | nullable (estándar Laravel) |

- `config/auth.php` apunta el provider `users` a `App\Models\Usuario`.
- Mutators normalizan `username` y `email` a minúsculas; `password` usa el cast `hashed`.
- Relaciones: `rol()` belongsTo `Rol`.
- Método `tienePermiso(string $identificador): bool` — carga una vez por instancia los
  identificadores de `rol->permisos` y comprueba pertenencia al conjunto.
- La cuenta de Alumno es opcional y se crea manualmente por el Administrador (ver spec,
  Assumptions); no existe vínculo automático con Matrícula en este módulo.

### Estados del usuario

```text
          crear
  (nuevo) ─────▶ ACTIVO ◀──── reactivar ────┐
                   │                         │
                   └──── desactivar ────▶ INACTIVO
```

- **ACTIVO**: puede iniciar sesión.
- **INACTIVO**: login rechazado con mensaje "cuenta inactiva"; cualquier sesión abierta se cierra
  en la siguiente solicitud (middleware `EnsureUserIsActive`).
- `desactivar` está prohibido si: (a) el usuario objetivo es el propio usuario autenticado, o
  (b) dejaría cero cuentas administradoras (ver regla abajo).

## Tablas estándar de Laravel que se conservan

- `sessions` (driver de sesión `database`, `SESSION_LIFETIME=120`), `cache`, `jobs`.
- Se elimina `password_reset_tokens` (no hay recuperación de contraseña en el MVP).

## Regla derivada: cuenta administradora

Una **cuenta administradora** es un usuario con `activo = true` cuyo rol tiene los permisos
`usuarios.editar` **y** `roles.asignar_permisos`. El sistema rechaza (error de validación) cualquier
operación que deje 0 cuentas administradoras:

| Operación | Se verifica |
|---|---|
| Desactivar usuario | ¿el usuario es la única cuenta administradora? |
| Cambiar rol de usuario | ¿pierde la condición y era la única? |
| Quitar `usuarios.editar` o `roles.asignar_permisos` a un rol | ¿quedaría alguna cuenta administradora? |

## Filtros de pertenencia (sin tablas en este módulo)

- No hay tablas nuevas. La declaración "este modelo requiere pertenencia" se hace usando el trait
  `RequierePertenencia` en el modelo; la regla se registra en `config/pertenencia.php`.
- Comportamiento fail-closed: modelo con el trait y sin regla → 403. Detalle del contrato en
  [contracts/pertenencia.md](contracts/pertenencia.md).

## Datos iniciales (seeders)

| Seeder | Crea | En `DatabaseSeeder` |
|---|---|:-:|
| `RolesYPermisosSeeder` | 5 roles, 7 permisos base, asignaciones (ver research R-07). Idempotente (`firstOrCreate` por `nombre` / `identificador`). | Sí |
| `AdministradorInicialSeeder` | Cuenta Administrador desde `SGE_ADMIN_*`; no sobrescribe si existe. | Sí |
| `UsuariosDemoSeeder` | 1 usuario por rol para demos y pruebas manuales. | Solo `APP_ENV=local` |
| `LoadTestSeeder` | 100 usuarios `carga001`…`carga100` para k6. | No (se ejecuta explícitamente) |

Nota sobre idempotencia: `RolesYPermisosSeeder` asigna un permiso base a sus roles **solo cuando
ese permiso se crea por primera vez** (`wasRecentlyCreated`). Re-ejecutarlo no duplica nada y no
revierte cambios que el Administrador haya hecho después en las asignaciones.
