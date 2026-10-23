# Ensayo local de `creditos-cpu.sh` y de `metrics.sh cierre` con B2als_v2 (T071c)

Fecha: 2026-10-20 · Sin Azure: `SGE_AZ_JSON` apunta a una respuesta sintética con el formato de
`az monitor metrics list` (60 minutos; 10 min en reposo con 30 créditos y luego CPU al 100 % consumiendo
1,4 créditos/min); `SGE_VMSIZE_FILE`/`SGE_LOG_DIR` temporales para `metrics.sh`. **No es una medición de
Azure**: valida el análisis, no los valores reales.

| Comprobación | Resultado |
|---|---|
| `creditos-cpu.sh 18:00Z 18:59Z 18:10Z` | CSV de 60 filas; `creditos-resumen.json` con `creditos_agotados: true`, primer minuto agotado 18:32Z = **minuto 22 de la rampa** (30 / 1,4 ≈ 21,4), 28 min con CPU limitada; imprime el aviso de agotamiento |
| Argumentos inválidos (`x y`) | Error de uso, salida ≠ 0 |
| `metrics.sh cierre` con la VM en `Standard_B2als_v2` | `EVIDENCIA INCOMPLETA`, salida 1 |
| `metrics.sh cierre` tras volver a `Standard_B2ats_v2` | `evidencia_completa: true`, salida 0; cuenta las muestras en `Standard_B2als_v2` (0,33 h, 0,02 USD) y recuerda ejecutar `creditos-cpu.sh` |

Pendiente (requiere Azure): confirmar que `az monitor metrics list --metric "CPU Credits Remaining" ...` devuelve
datos para B2als_v2 (se comprueba en la prueba de ida y vuelta, T071a). `shellcheck` no está instalado en esta
máquina; solo `bash -n`.
