# Contrato: Rutas web

**Feature**: `001-auth-roles-rbac`

Todas las rutas son HTML (Blade/Livewire) sobre sesión con protección CSRF. No hay API JSON en este
módulo. "Permiso" = identificador que se verifica contra `rol_permiso` con el middleware
`can:<permiso>`; si falta, la respuesta es **403**.

## Públicas (middleware `guest`)

| Método | URI | Nombre | Comportamiento |
|---|---|---|---|
| GET | `/login` | `login` | Formulario con campos `login` (correo o usuario) y `password`. |
| POST | `/login` | `login.store` | Ver tabla de resultados abajo. |

Resultados de `POST /login`:

| Caso | Respuesta |
|---|---|
| Credenciales válidas, cuenta activa | 302 → `/inicio`; sesión regenerada; limpia contador de intentos. |
| Credenciales inválidas o usuario inexistente | 302 → `/login` con error en `login`: "Las credenciales no son válidas." (mensaje idéntico en ambos casos). |
| Credenciales válidas, cuenta inactiva | 302 → `/login` con error en `login`: "Tu cuenta está inactiva. Contacta al administrador."; sin sesión; cuenta como intento fallido para el límite. |
| 6.º intento fallido (incluye cuenta inactiva) en 60 s para el mismo `login`+IP | 302 → `/login` con error "Demasiados intentos. Intenta nuevamente en N segundos." |
| Campos vacíos | 302 → `/login` con errores de validación. |

Un usuario autenticado que visita `/login` es redirigido a `/inicio`.

## Autenticadas (middleware `auth` + `activo`)

Un visitante no autenticado es redirigido a `/login`. Un usuario inactivo es deslogueado y
redirigido a `/login` con el mensaje de cuenta inactiva. El middleware `activo`
(`EnsureUserIsActive`) también se registra como middleware persistente de Livewire, así que aplica
igual a las peticiones de acciones Livewire; además, una cuenta inactiva no tiene ningún permiso.

| Método | URI | Nombre | Permiso | Vista / componente |
|---|---|---|---|---|
| POST | `/logout` | `logout` | — | Cierra sesión, invalida y regenera token → 302 `/login`. |
| GET | `/` | — | — | 302 → `/inicio`. |
| GET | `/inicio` | `inicio` | — | Página de inicio (placeholder para módulos futuros). |
| GET | `/admin/usuarios` | `admin.usuarios.index` | `usuarios.ver` | Livewire `Admin\Usuarios\Index` |
| GET | `/admin/usuarios/crear` | `admin.usuarios.create` | `usuarios.crear` | Livewire `Admin\Usuarios\Form` |
| GET | `/admin/usuarios/{usuario}/editar` | `admin.usuarios.edit` | `usuarios.editar` | Livewire `Admin\Usuarios\Form` |
| GET | `/admin/permisos` | `admin.permisos.index` | `roles.ver` | Livewire `Admin\Permisos\Index` |
| GET | `/admin/roles` | `admin.roles.index` | `roles.ver` | Livewire `Admin\Roles\Index` |
| GET | `/admin/roles/{rol}/permisos` | `admin.roles.permisos` | `roles.ver` | Livewire `Admin\Roles\Permisos` |

Las rutas `/admin/...` son páginas completas de Livewire 4 y se registran con `Route::livewire()`
(no `Route::get()`), por ejemplo:
`Route::livewire('/admin/usuarios', Admin\Usuarios\Index::class)->name('admin.usuarios.index')->middleware('can:usuarios.ver');`
`login`, `logout` e `inicio` son rutas Blade/controlador normales con `Route::get`/`Route::post`.

Las acciones que modifican datos se ejecutan dentro de los componentes Livewire y vuelven a
verificar su propio permiso (ver [componentes-livewire.md](componentes-livewire.md)); proteger la
página no basta porque las acciones de Livewire son peticiones independientes.

## Menú (`config/menu.php`)

| Etiqueta | Ruta | Permiso requerido |
|---|---|---|
| Inicio | `inicio` | — (siempre visible) |
| Usuarios | `admin.usuarios.index` | `usuarios.ver` |
| Roles | `admin.roles.index` | `roles.ver` |
| Permisos | `admin.permisos.index` | `roles.ver` |

Visibilidad esperada con los datos iniciales:

| Rol | Ítems visibles |
|---|---|
| Administrador | Inicio, Usuarios, Roles, Permisos |
| Director | Inicio, Usuarios, Roles, Permisos (solo lectura) |
| Docente, Padre de Familia, Alumno | Inicio |
