# Sesión de carga de 2000 usuarios — AAAA-MM-DD  (plantilla; copiar como `k6-sesion-<fecha>.md`)

Procedimiento: [`docs/despliegue/sesion-carga-2000.md`](../../../docs/despliegue/sesion-carga-2000.md) (enmienda 2.4.0).
**La sesión solo vale como evidencia si la casilla 16 está marcada y `cierre-carga.json` dice `evidencia_completa: true`.**

- Versión desplegada: `v…` · Perfil: `carga2000` · Tamaño de la VM: `Standard_B8als_v2` · `CARGA_TOTAL`: `2000`
- Respaldo previo: `sge-…-manual.sql.gz` · Usuarios antes: `…` · Usuarios tras restaurar: `…`
- Inicio en B8als_v2 (facturación, UTC): `…` · Arranque tras el Resize (`<desde-utc>`): `…` · Inicio de la rampa (UTC): `…` · Fin (regreso a B2ats_v2): `…`
- Run ID del humo: `…` · Run ID de la rampa: `…` · Artefacto: `evidencia-carga-…`

## Lista de verificación

- [ ] 1. Respaldo manual copiado a la PC y número de usuarios anotado
- [ ] 2. `az`: deallocate, resize a B8als_v2, start
- [ ] 3. `PERFIL_TAMANO=carga2000` + `deploy.sh --reconfigurar` + `/up` correcto
- [ ] 4. `metrics.sh tamano` = `Standard_B8als_v2`, 8 vCPU, ≈ 16 GiB
- [ ] 5. Siembra con `CARGA_TOTAL=2000` (2020 cuentas activas)
- [ ] 6. Humo en verde (`carga_total=2000`)
- [ ] 7. **Créditos de partida** ≥ 400 (valor: `…` a las `…` UTC; espera: `…`)
- [ ] 8. `metrics.sh start`
- [ ] 9. Rampa completa (`carga_total=2000`)
- [ ] 10. `metrics.sh stop` + evidencias descargadas
- [ ] 11. `creditos-cpu.sh` ejecutado
- [ ] 12. Evidencia copiada al repositorio y resumida abajo
- [ ] 13. Cuentas `carga*` desactivadas (impreso: `2020`)
- [ ] 14. Base restaurada (usuarios = antes; cuentas `carga*` activas = 0)
- [ ] 15. `PERFIL_TAMANO=diario` + `deploy.sh --reconfigurar`
- [ ] 16. **Regreso a B2ats_v2** + `metrics.sh cierre` → `evidencia_completa: true`

## Resultados (umbrales del Módulo 5, sin cambios)

| Métrica | Umbral | Medido | ¿Cumple? |
|---|---|---|---|
| Login p95 (`login`) | < 3,5 s | | |
| Consulta de notas p95 (`consulta`) | < 2 s | | |
| Registro de notas p95 (`notas`) | < 4 s | | |
| Boleta p95 (`boleta`) | < 8 s | | |
| Solicitudes fallidas | < 1 % | | |

## Recursos de la VM durante la rampa (de `metricas-*.csv`)

| Métrica | p95 | Máximo |
|---|--:|--:|
| CPU del servidor (%) | | |
| Memoria usada (MB) | | |
| Swap usado (MB) | | |
| CPU `app` / `db` / `web` (%) | | |

## Generador de carga (de `runner-resumen.json`)

| vCPU del runner | CPU p95 / máx (%) | Memoria p95 / máx (MB) | ¿Cuello de botella? |
|--:|--|--|---|
| | | | |

## Créditos de CPU (de `creditos-resumen.json`)

Créditos al iniciar: `…` · mínimo: `…` · ¿agotados?: `…` · minuto de la rampa en que la CPU quedó limitada: `…`

## Análisis y decisiones

(Cuello de botella, qué se ajustó del perfil `carga2000`, qué criterio queda cumplido o no, y costo real frente al estimado.)
