# Verificación manual del Módulo 1 en staging

**Fecha**: 2026-10-21 · **URL**: `https://sge-peru.chilecentral.cloudapp.azure.com` · **Versión desplegada** (`/up`): `v0.2.0` · **Navegador**: …
**Quién**: el desarrollador · **Tamaño de la VM**: `Standard_B2ats_v2`
**Referencia**: [quickstart.md §3](../quickstart.md#3-verificación-manual) (11 pasos).

| # | Paso | Resultado esperado | ¿Pasó? | Observaciones |
|---|---|---|:-:|---|
| 1 | Entrar con `SGE_ADMIN_USERNAME` y luego con `SGE_ADMIN_EMAIL` | Ambos acceden a `/inicio`; menú Inicio, Usuarios, Roles, Permisos | ✔ | Sin observaciones |
| 2 | Contraseña incorrecta | «Las credenciales no son válidas.» | ✔ | Sin observaciones |
| 3 | Admin crea `docente1` (rol Docente) | Aparece activo en la lista | ✔ | Sin observaciones |
| 4 | Entrar como `docente1`; abrir `/admin/usuarios` | Menú solo con Inicio; 403 | ✔ | Menú solo con Inicio. `/admin/usuarios` muestra la página `errors/403` personalizada («Acceso denegado — No tienes permiso para realizar esta acción»), no el texto «403» suelto |
| 5 | Admin da `usuarios.ver` al rol Docente; recargar la sesión de `docente1` | Aparece «Usuarios» sin volver a iniciar sesión; quitarlo después | ✔ | Sin observaciones |
| 6 | Admin desactiva `docente1` con sesión abierta | Siguiente clic → `/login` con «Tu cuenta está inactiva» | ✔ | Sin observaciones |
| 7 | Acción Livewire con la cuenta ya desactivada, sin recargar | 403, no se ejecuta | ✔ | Sin observaciones |
| 8 | Admin intenta desactivar su propia cuenta | «No puedes desactivar tu propia cuenta» | ✔ | Sin observaciones |
| 9 | Quitar `roles.asignar_permisos` al rol Administrador (único admin) | Error: dejaría el sistema sin administradores | ✔ | Sin observaciones |
| 10 | Crear el permiso `reportes.ver` y editar su nombre | Se guarda; el identificador no es editable | ✔ | El identificador `reportes.ver` se muestra como texto fijo, no editable. El nombre se editó en línea («Ver reportes» → «Ver reportes académicos»), se guardó y persiste tras recargar |
| 11 | 6 intentos fallidos seguidos con el mismo usuario | El 6.º muestra «Demasiados intentos…» | ✔ | Los 6 intentos fallidos se hicieron con `docente1`; el sexto mostró «Demasiados intentos…» |

## Resultado

- Pasos cumplidos: **11 / 11**
- Diferencias respecto a la ejecución local del Módulo 1 (`verificacion-manual.md`): ninguna de comportamiento. En staging la página 403 es la personalizada `errors/403` (paso 4).
- Criterio DC-008 (spec 002): **cumplido** (11/11 pasos y peor caso de SC-007 del Módulo 1 de 57,71 s < 60 s, ver `staging-sc007.md`)
