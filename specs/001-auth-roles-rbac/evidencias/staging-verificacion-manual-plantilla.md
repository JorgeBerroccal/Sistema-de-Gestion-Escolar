# Verificación manual del Módulo 1 en staging (plantilla; copiar como `staging-verificacion-manual.md`)

**Fecha**: AAAA-MM-DD · **URL**: `https://<fqdn>` · **Versión desplegada** (`/up`): `v…` · **Navegador**: …
**Quién**: el desarrollador · **Tamaño de la VM**: `Standard_B2ats_v2`
**Referencia**: [quickstart.md §3](../quickstart.md#3-verificación-manual) (11 pasos).

| # | Paso | Resultado esperado | ¿Pasó? | Observaciones |
|---|---|---|:-:|---|
| 1 | Entrar con `SGE_ADMIN_USERNAME` y luego con `SGE_ADMIN_EMAIL` | Ambos acceden a `/inicio`; menú Inicio, Usuarios, Roles, Permisos | | |
| 2 | Contraseña incorrecta | «Las credenciales no son válidas.» | | |
| 3 | Admin crea `docente1` (rol Docente) | Aparece activo en la lista | | |
| 4 | Entrar como `docente1`; abrir `/admin/usuarios` | Menú solo con Inicio; 403 | | |
| 5 | Admin da `usuarios.ver` al rol Docente; recargar la sesión de `docente1` | Aparece «Usuarios» sin volver a iniciar sesión; quitarlo después | | |
| 6 | Admin desactiva `docente1` con sesión abierta | Siguiente clic → `/login` con «Tu cuenta está inactiva» | | |
| 7 | Acción Livewire con la cuenta ya desactivada, sin recargar | 403, no se ejecuta | | |
| 8 | Admin intenta desactivar su propia cuenta | «No puedes desactivar tu propia cuenta» | | |
| 9 | Quitar `roles.asignar_permisos` al rol Administrador (único admin) | Error: dejaría el sistema sin administradores | | |
| 10 | Crear el permiso `reportes.ver` y editar su nombre | Se guarda; el identificador no es editable | | |
| 11 | 6 intentos fallidos seguidos con el mismo usuario | El 6.º muestra «Demasiados intentos…» | | |

## Resultado

- Pasos cumplidos: _ / 11
- Diferencias respecto a la ejecución local del Módulo 1 (`verificacion-manual.md`): …
- Criterio DC-008 (spec 002): _ (los 11 pasos y el peor caso de SC-007 del Módulo 1 < 60 s, ver `staging-sc007.md`)
