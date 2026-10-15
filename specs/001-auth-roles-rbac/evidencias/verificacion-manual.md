# Verificación manual del Módulo 1 (T097)

**Fecha**: 2026-10-14
**Entorno**: local (Docker Compose, `APP_ENV=local`, OPcache con `validate_timestamps=1`), navegador.
**Quién**: el desarrollador (registrado a partir de su informe; el asistente no presenció la ejecución).
**Alcance**: pasos de [quickstart.md §3](../quickstart.md#3-verificación-manual) (la tabla incluye además el paso 11, límite de intentos) y SC-007.

## Resultado

| Verificación | Resultado |
|---|---|
| Pasos de quickstart §3 | **Todos pasaron** en local. |
| §5 Reconstrucción desde cero (`migrate:fresh --seed` y segunda siembra) | Pasó (5 roles, 7 permisos, sin duplicados; verificado por el asistente el 2026-10-14). |
| **SC-007** — crear una cuenta con rol en menos de 1 minuto | Antes de T103: **no se cumplía** (1 min 10 s). Después de T103: **cumplido** (45 s). Ver abajo. |

## SC-007: historial de mediciones

Criterio de la spec: crear una cuenta y asignarle un rol en menos de 1 minuto. Método: cronómetro desde pulsar
«Crear usuario» hasta ver al usuario en el listado.

| Fecha | Versión del formulario | Promedio | Resultado |
|---|---|---|---|
| 2026-10-14 | Antes de T103 | **1 min 10 s** (3 intentos) | **No cumplido** (≈ 10 s por encima, ≈ 17 %) |
| 2026-10-14 | Después de T103 | **45 s** | **Cumplido** |

- **T103** (commit `5373f19`): sugerencia de username editable, autofocus en «Nombres» y correo opcional después del rol.
- La segunda medición es el promedio informado por el desarrollador; no se registró aquí el número de intentos ni el peor
  tiempo. La repetición en el servidor desplegado (junto con T097) debe registrarlos.
- **Estado final: SC-007 cumplido en local.**

## Pendiente

- Repetir esta verificación manual **en el servidor desplegado** (T097 solo queda verificada en local), incluida una nueva medición de SC-007 con peor caso.
- La prueba de carga de 1000 VUs (T095) sigue pendiente; ver `k6-humo.txt` para la prueba de humo.
