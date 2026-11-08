# Evidencia: prueba de humo en staging (Módulo 3, T042 / SC-006)

**Fecha**: 2026-11-04 · **Versión**: `v0.4.0` · Prueba de ~10 min hecha por el responsable del proyecto con el
Administrador y una cuenta de Padre de Familia ficticia (Constitución 2.2.0). Solo datos ficticios.

## Despliegue

- `./deploy/deploy.sh v0.4.0`: «Desplegada v0.4.0 en 52s», comprobación de salud correcta.
- `MatriculaDemoSeeder` terminó en DONE (ejecutó también `RolesYPermisosSeeder` y `TiposDocumentoSeeder`).
- Permisos en base: **20** = 19 del seeder + `reportes.ver`, creado a mano en el Módulo 1.

## Pasos (quickstart §2)

| Paso | Resultado | Observación |
|---|---|---|
| 1 | ✔ | El menú del Administrador muestra «Alumnos» y «Nóminas» |
| 2 | ✔ | DNI de 7 dígitos rechazado; de 8 dígitos aceptado |
| 3 | ✔ | DNI duplicado (mismo tipo y número) rechazado |
| 4 | ✔ | Matrícula individual y masiva: los tres alumnos aparecen en la nómina |
| 5 | ✔ | Retiro con motivo; la matrícula conserva el historial |
| 6 | ✔ | Cuenta de Padre de Familia vinculada a una ficha como Madre |
| 7 | ✔ | El Padre ve solo a su hijo en «Mis alumnos»; la ficha de otro alumno y `/matricula/nominas` dan 403 |

## Seguridad posterior

- Las 1000 cuentas `carga*` se desactivaron en staging (update de `activo` a false: 1000) porque su contraseña
  está en el script k6 del repositorio público. Cuentas `docente.demo*` en staging: 0.
- Para el Módulo 5, la lista de [prueba-de-carga.md](../../../docs/despliegue/prueba-de-carga.md) tiene ahora el
  paso 8 (reactivar al sembrar) y el paso 15 bis (desactivar antes del cierre).
