# Evidencia: recorrido en staging (Módulo 2, SC-006)

**Fecha**: 2026-11-04 · **Versión**: `v0.3.0` · Recorrido manual hecho por el responsable del proyecto.

| Ref. | Resultado | Observación |
|---|---|---|
| A1 | ✔ | «Asignaciones» aparece para el Administrador; «Mis asignaciones» no |
| A2 | ✔ | Usuarios de prueba creados con nombres propios, incluido un Director |
| A3 | ✔ | Rechaza guardar la institución sin niveles |
| A4 | ✔ | Año 2026 creado; 2027 con periodo superpuesto rechazado («El periodo «II Bimestre» se superpone con «I Bimestre» o no está en orden cronológico: cada día pertenece a un solo periodo.»); un solo año vigente; 2026 queda vigente |
| A5 | ✔ | |
| A6 | ✔ | Asignaciones masivas válidas. El caso inválido no se puede provocar desde la interfaz (el formulario solo ofrece cursos sin docente en la sección); cubierto por pruebas automáticas |
| A7 | ✔ | «Eliminar» no se muestra en cursos con asignaciones; solo «Desactivar» |
| B | ✔ | |
| C | ✔ | |
| D (fila 10) | ✔ | `academico.ver` quitado al rol Director |
| D (fila 9) | — | No ejecutada; cubierta por pruebas automáticas (`PermisosPorDatosTest`) |

## Segundo despliegue de `v0.3.0` (quickstart §3, paso 3)

Hecho el 2026-11-04 con `./deploy/deploy.sh v0.3.0` sobre la misma versión.

- `sge:sincronizar-base` corrió sin errores («Datos base sincronizados»); `RolesYPermisosSeeder` y
  `EscalasCalificacionSeeder` en verde. Despliegue en 21 s.
- `/up` informa `version=v0.3.0`; `despliegues.log` registra dos entradas OK de `v0.3.0` (43 s y 21 s).
- Permisos en base tras el segundo despliegue: **14**; el seeder define 13, así que hay 1 más. Pendiente
  confirmar que es un permiso creado a mano durante las pruebas y no un duplicado (el seeder usa
  `firstOrCreate` por identificador, que además es único).
- El Director **siguió sin** `academico.ver` (no se revirtió el cambio del Administrador); el año 2026 sigue
  vigente y las asignaciones se conservan. Después de comprobarlo se devolvió `academico.ver` al Director.

**Resultado**: SC-006 cumplido; T064 cerrada.
