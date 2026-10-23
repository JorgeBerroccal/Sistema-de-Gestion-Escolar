# Prueba de humo de carga contra staging (T061, FR-018)

Fecha: 23/10/2026. Versión desplegada: `v0.2.0` (coincide con `/up`). Tamaño de VM: `Standard_B2ats_v2` (el diario). Workflow `carga` con `humo=true` (10 VUs, ~35 s), ejecución 40022045750, commit `2037f1a`.

## Preparación

| Paso | Resultado |
|---|---|
| `LoadTestSeeder` en el servidor (`docker compose -p sge -f docker-compose.prod.yml exec app php artisan db:seed --class=LoadTestSeeder --force`) | DONE en 750 ms |
| Edge case «sin `STAGING_URL`»: se borró la variable y se lanzó el workflow (ejecución 40021954159) | Falló en «Comprobar objetivo» con «Falta la variable de repositorio STAGING_URL…» |
| Variable restaurada con `https://sge-peru.chilecentral.cloudapp.azure.com` | `gh variable list` la muestra igual que antes |

## Resultado

| Métrica | Valor | Umbral |
|---|---|---|
| Checks | 204 de 204 (100 %) | — |
| `http_req_failed` | 0,00 % (0 de 418) | < 1 % |
| Login p95 | 385,99 ms | < 3,5 s |
| Consultas p95 | 155,12 ms | < 1,5 s |
| Iteraciones | 107 | — |

Umbrales de k6: cumplidos. El login desde internet funciona (los checks «login termina en /inicio», «inicio 200» y «usuarios 200» pasan en todas las iteraciones). Artefacto: `k6-humo-staging-salida.txt` y `k6-humo-staging-meta.json`.

Esto es solo el humo: no cierra SC-006, que exige la rampa de 1000 VUs (T075).
