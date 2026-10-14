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
| **SC-007** — crear una cuenta con rol en menos de 1 minuto | **NO se cumple.** |

## SC-007: no cumplido

- **Medición**: crear una cuenta y asignarle un rol tomó en promedio **1 min 10 s** en tres intentos, medido desde pulsar «Crear usuario» hasta ver al usuario en el listado.
- **Umbral de la spec**: menos de 1 minuto. Se excede en unos 10 s (≈17 %).
- **Estado**: abierto. No se implementó ningún cambio. Las mejoras de usabilidad candidatas están pendientes de aprobación (formulario `resources/views/livewire/admin/usuarios/form.blade.php`).

## Pendiente

- Repetir esta verificación manual **en el servidor desplegado** (T097 solo queda verificada en local).
- La prueba de carga de 1000 VUs (T095) sigue pendiente; ver `k6-humo.txt` para la prueba de humo.

## Seguimiento de SC-007 (T103)

2026-10-14: implementadas tres mejoras de usabilidad en el formulario de creación (sugerencia de username editable,
autofocus en «Nombres», correo opcional después del rol). **Pendiente volver a medir** SC-007 en el navegador; hasta
entonces el criterio sigue sin cumplirse.
