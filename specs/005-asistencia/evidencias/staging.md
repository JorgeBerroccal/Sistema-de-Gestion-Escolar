# Evidencia: prueba de humo en staging (Módulo 4, T032 / SC-002, SC-003)

**Fecha**: 2026-11-07 · **Versión**: `v0.5.0` · Prueba de ~10 min hecha por el responsable del proyecto con el
Administrador y una cuenta de Padre de Familia ficticia (Constitución 2.2.0). Solo datos ficticios.

## Despliegue

- `./deploy/deploy.sh v0.5.0`: resultado no detallado en el reporte del responsable.
- `AsistenciaDemoSeeder`: no detallado en el reporte del responsable.
- Permisos en base: no detallado en el reporte (esperado: 24).

## Pasos (quickstart §2)

| Paso | Resultado | Observación |
|---|---|---|
| 1 | ✔ | Tomar asistencia: sección con alumnos y día hábil pasado; todos «presente» |
| 2 | ✔ | Falta y tardanza guardadas; al reabrir persisten |
| 3 | ✔ | Sábado: aviso y sin botón de guardar |
| 4 | ✔ | Falta justificada con motivo |
| 5 | ✔ | Reporte de la sección y rango: totales y porcentaje coherentes |
| 6 | ✔ | Padre ve solo a su hijo, con resumen y sin controles de edición |
| 7 | ✔ | Sección o alumno ajeno por URL: 403 |

## Seguridad posterior

- Cuentas `carga*` en staging: **0** activas (0 antes y 0 después) (deben seguir desactivadas hasta la sesión k6 del Módulo 5).
- Cuenta ficticia de Padre creada para la prueba: **desactivada** al terminar.
