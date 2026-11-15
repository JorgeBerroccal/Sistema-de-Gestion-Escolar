# T031: despliegue de v0.7.0 en staging y prueba de humo del asistente

Estado: **en curso** (restauración hecha; despliegue y prueba de humo pendientes). Horas en Lima (UTC−5). Datos ficticios.

## Antes del despliegue: restauración del respaldo de v0.6.0

Se restauró `sge-20261112-095609-manual.sql.gz` (anterior a la carga de datos de la sesión k6) para quitar los datos de la sesión k6, antes de desplegar porque el respaldo es de v0.6.0.

| Comprobación | Resultado |
|---|---|
| `restore.sh` (14/11, 20:55) | «Restauración completada en 15 s. Usuarios en la base: 1015» |
| Cuentas `carga%` activas | 0 |
| Permisos en la base | 31 (el esperado antes de v0.7.0: 30 del seeder + `reportes.ver` manual; v0.7.0 añade `asistente.usar`, hasta 32) |

Pendiente de comprobar tras la restauración: que sigan existiendo el I Bimestre de Primaria · 1.° A de Luna Demo 1 (cerrado, con notas) y la vinculación de María Torres Gómez con Luna (quickstart §3); no se ha registrado ese resultado.

## Despliegue de v0.7.0

- [x] Workflow `release` de `v0.7.0` en verde (run 43665148159, imágenes publicadas el 14/11 por la noche en Lima)
- [ ] `./deploy/deploy.sh v0.7.0` (hora, resultado, `/up` con `v0.7.0`)
- [ ] `sge:sincronizar-base` creó `asistente.usar` (permisos: 32)
- [ ] Variables del asistente en el `.env` (la clave no se registra) y `./deploy/deploy.sh --reconfigurar`

## Prueba de humo (`quickstart.md` §3)

| # | Prueba | Resultado |
|---|---|---|
| 1 | María Torres nombrando a Luna Demo 1: promedio y faltas, comparados con «Mis notas» y «Mi asistencia» | pendiente |
| 2 | María con varios hijos, «¿cómo van las notas?» sin nombrar hijo ⇒ pide aclaración | pendiente |
| 3 | Notas de un alumno ajeno ⇒ rechazo neutro, sin datos (R-13) | pendiente |
| 4 | Alumno (Luna Demo 1): «¿cómo voy?», aviso de IA y español | pendiente |
| 5 | Límite por usuario ⇒ aviso con tiempo de espera | pendiente |
| 6 | Docente/Director/Administrador no ven «Asistente virtual» | pendiente |

Hallazgos (se corrigen en `v0.7.1`, sin k6): ninguno registrado todavía.
