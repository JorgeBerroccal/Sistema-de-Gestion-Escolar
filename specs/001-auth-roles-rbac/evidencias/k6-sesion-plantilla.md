# Sesión de prueba de carga — AAAA-MM-DD  (plantilla; copiar como `k6-sesion-<fecha>.md`)

Procedimiento: [`docs/despliegue/prueba-de-carga.md`](../../../docs/despliegue/prueba-de-carga.md).
**La sesión solo vale como evidencia si la casilla 14 está marcada y `cierre-carga.json` dice
`evidencia_completa: true`.**

- Versión desplegada: `v…` · Perfil: `carga` · Tamaño de la VM: `Standard_D2s_v4`
- Inicio en D2s_v4 (hora de facturación): `…` · Fin (regreso a B2ats_v2): `…`
- Run ID de GitHub Actions (rampa completa): `…` · Artefacto: `evidencia-carga-…`

## Lista de verificación

- [ ] 1. Cuota DSv4 y holgura de crédito ≥ 5 USD
- [ ] 2. Respaldo manual
- [ ] 3. Stop (deallocated)
- [ ] 4. Resize a D2s_v4
- [ ] 5. Start
- [ ] 6. `PERFIL_TAMANO=carga` + `deploy.sh --reconfigurar` + `/up` correcto
- [ ] 7. `metrics.sh tamano` = `Standard_D2s_v4`
- [ ] 8. `LoadTestSeeder` (1000 usuarios)
- [ ] 9. Humo en verde
- [ ] 10. `metrics.sh start` + rampa completa
- [ ] 11. `metrics.sh stop` + evidencias descargadas
- [ ] 12. Evidencia copiada al repositorio y resumida abajo
- [ ] 13. `PERFIL_TAMANO=diario` + `deploy.sh --reconfigurar`
- [ ] 14. **Regreso a B2ats_v2** + `metrics.sh cierre` → `evidencia_completa: true`

## Resultados (umbrales de SC-006 del Módulo 1)

| Métrica | Umbral | Medido | ¿Cumple? |
|---|---|---|---|
| Login p95 (`login`) | < 3,5 s | | |
| Consultas p95 (`consulta`) | < 1,5 s | | |
| Solicitudes fallidas | < 1 % | | |

## Recursos del servidor durante la rampa (de `metricas-*.csv`)

| Métrica | p95 | Máximo |
|---|--:|--:|
| CPU del servidor (%) | | |
| Memoria usada (MB) | | |
| Swap usado (MB) | | |
| CPU / memoria `app` | | |
| CPU / memoria `db` | | |

Modelo de CPU (`meta-servidor.json`): `…`

## Observaciones

- ¿Servidor colgado? (hora, causa probable, qué se hizo)
- Análisis si no se cumplen los umbrales (cuello de botella, ajustes del perfil `carga`)
- Costo de la sesión (horas × 0,1341 USD): `…` (de `cierre-carga.json`: `…`)
