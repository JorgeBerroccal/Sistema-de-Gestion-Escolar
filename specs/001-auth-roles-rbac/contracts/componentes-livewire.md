# Contrato: Componentes Livewire y acciones protegidas

**Feature**: `001-auth-roles-rbac`

Cada acción que modifica datos llama a `$this->authorize('<permiso>')` al inicio. Sin el permiso la
acción responde **403** y no cambia nada. Los botones de acciones no permitidas no se muestran
(`@can`), pero la verificación en servidor es la que cuenta. Cada fila de estas tablas requiere un
Feature test de acceso permitido y uno de acceso denegado (SC-002).

## `Admin\Usuarios\Index` — lista de usuarios

Requiere `usuarios.ver` para montar.

| Acción | Permiso | Entradas | Resultado / errores |
|---|---|---|---|
| `buscar` (propiedad `q`) | `usuarios.ver` | texto | Filtra por nombres, apellidos, username o email. Paginación de 15. |
| `desactivar(id)` | `usuarios.desactivar` | id de usuario | `activo=false`. Error si es el propio usuario ("No puedes desactivar tu propia cuenta") o si es la última cuenta administradora. |
| `reactivar(id)` | `usuarios.desactivar` | id de usuario | `activo=true`. |

Columnas: nombres y apellidos, username, email, rol, estado.

## `Admin\Usuarios\Form` — crear / editar usuario

| Acción | Permiso | Entradas | Validación / resultado |
|---|---|---|---|
| `guardar` (crear) | `usuarios.crear` | nombres, apellidos, username, email?, password, password_confirmation, rol_id | nombres/apellidos requeridos ≤100; username requerido, `^[a-z0-9._-]{3,50}$`, único; email opcional, formato válido, único; password ≥8 y confirmada; rol_id existe. Crea usuario activo → redirige a la lista. |
| `guardar` (editar) | `usuarios.editar` | nombres, apellidos, username, email?, rol_id | Mismas reglas (únicos ignorando al propio usuario). Si cambia `rol_id`, aplica la protección de cuenta administradora. |
| `restablecerPassword` | `usuarios.editar` | password, password_confirmation | ≥8 y confirmada. La contraseña anterior deja de funcionar. |

`username` y `email` se normalizan a minúsculas antes de validar unicidad.

## `Admin\Permisos\Index` — catálogo de permisos

Requiere `roles.ver` para montar (solo lectura para quien no tenga `permisos.gestionar`).

| Acción | Permiso | Entradas | Validación / resultado |
|---|---|---|---|
| `crear` | `permisos.gestionar` | identificador, nombre, descripcion? | identificador requerido, `^[a-z_]+(\.[a-z_]+)+$`, único; nombre requerido ≤100; descripcion ≤255. |
| `actualizar(id)` | `permisos.gestionar` | nombre, descripcion? | El identificador **no** se puede cambiar (no forma parte de la entrada). |

## `Admin\Roles\Index` — lista de roles

Requiere `roles.ver`. Muestra nombre, descripción, cantidad de usuarios y de permisos; enlaza a
`admin.roles.permisos`. Sin acciones de escritura.

## `Admin\Roles\Permisos` — permisos de un rol

Requiere `roles.ver` para montar (casillas deshabilitadas sin `roles.asignar_permisos`).

| Acción | Permiso | Entradas | Validación / resultado |
|---|---|---|---|
| `guardar` | `roles.asignar_permisos` | lista de ids de permiso | Todos existen. `sync` sobre `rol_permiso`. Error si quitar `usuarios.editar` o `roles.asignar_permisos` deja cero cuentas administradoras. El cambio aplica a los usuarios del rol en su siguiente solicitud. |
