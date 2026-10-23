# Sesión de prueba de carga — AAAA-MM-DD  (plantilla; copiar como `k6-sesion-<fecha>.md`)

Procedimiento: [`docs/despliegue/prueba-de-carga.md`](../../../docs/despliegue/prueba-de-carga.md).
**La sesión solo vale como evidencia si la casilla 16 está marcada y `cierre-carga.json` dice
`evidencia_completa: true`.**

- Versión desplegada: `v…` · Perfil: `carga` · Tamaño de la VM: `Standard_B2als_v2`
- Inicio en B2als_v2 (hora de facturación, UTC): `…` · Arranque tras el Resize (`<desde-utc>`): `…` · Inicio de la rampa (UTC): `…` · Fin (regreso a B2ats_v2): `…`
- Run ID de GitHub Actions (rampa completa): `…` · Artefacto: `evidencia-carga-…`

## Lista de verificación

- [ ] 1. Cuota BASv2 y holgura de crédito ≥ 5 USD
- [ ] 2. Respaldo manual
- [ ] 3. Stop (deallocated)
- [ ] 4. Resize a B2als_v2
- [ ] 5. Start
- [ ] 6. `PERFIL_TAMANO=carga` + `deploy.sh --reconfigurar` + `/up` correcto
- [ ] 7. `metrics.sh tamano` = `Standard_B2als_v2`
- [ ] 8. `LoadTestSeeder` (1000 usuarios)
- [ ] 9. Humo en verde
- [ ] 10. **Créditos de partida** ≥ 200 (valor: `…` a las `…` UTC; espera si hizo falta: `…`)
- [ ] 11. `metrics.sh start` + rampa completa
- [ ] 12. `metrics.sh stop` + evidencias descargadas
- [ ] 13. `creditos-cpu.sh` ejecutado (CSV y `creditos-resumen.json` guardados)
- [ ] 14. Evidencia copiada al repositorio y resumida abajo
- [ ] 15. `PERFIL_TAMANO=diario` + `deploy.sh --reconfigurar`
- [ ] 16. **Regreso a B2ats_v2** + `metrics.sh cierre` → `evidencia_completa: true`

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

## Créditos de CPU (de `creditos-resumen.json`)

| Dato | Valor |
|---|---|
| Créditos al iniciar la rampa | |
| Créditos mínimos y hora | |
| ¿Se agotaron? | |
| Minuto de la rampa en que la CPU quedó limitada (si se agotaron) | |
| Minutos con CPU limitada | |

Si se agotaron: los resultados de arriba posteriores a ese minuto se obtuvieron con la CPU limitada al 30 % y se
analizan aparte; la sesión se registra igual.

## Observaciones

- ¿Servidor colgado? (hora, causa probable, qué se hizo)
- Análisis si no se cumplen los umbrales (cuello de botella, ajustes del perfil `carga`)
- Costo de la sesión (horas × 0,0526 USD): `…` (de `cierre-carga.json`: `…`)
