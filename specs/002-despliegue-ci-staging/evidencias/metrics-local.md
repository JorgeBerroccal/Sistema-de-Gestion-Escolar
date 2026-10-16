# Ensayo local de metrics.sh y registro-tamano.sh (T039)

Fecha: 2026-10-16 · `SGE_VMSIZE_FILE` simulando el servicio de metadatos de Azure, `SGE_LOG_DIR`/`SGE_DIR`
temporales, `SGE_METRICS_INTERVAL=1`, contenedores de desarrollo reales para las columnas `ctr_*`.

| Comprobación | Resultado |
|---|---|
| `registro-tamano.sh` | Añade `<UTC> <vmSize>` a `tamano.log` |
| `metrics.sh start` / `stop` | CSV con las 14 columnas y filas reales de CPU/memoria/swap/contenedores; resumen p95/máximo; `meta-servidor.json` con `vm_size`, vCPU, RAM, modelo de CPU, versión, perfil, inicio y fin |
| CPU del servidor | Con un bucle ocupando un núcleo durante 6 s: p95 56,7 %, máximo 65,1 % |
| `metrics.sh cierre` con la VM aún en `Standard_D2s_v4` | `EVIDENCIA INCOMPLETA`, salida 1, **no** escribe `cierre-carga.json` |
| `metrics.sh cierre` tras volver a `Standard_B2ats_v2` | `evidencia_completa: true`, salida 0, `cierre-carga.json` con horas y costo aproximados |

Defectos encontrados y corregidos en el ensayo: (1) el muestreador moría al primer ciclo por `set -e` +
`pipefail` con una tubería cuyo `awk` terminaba antes de leer toda la entrada; ahora el proceso de larga
duración tolera fallos puntuales; (2) el cálculo de CPU% devolvía vacío porque `printf` con un operador `>`
dentro de `awk` se interpreta como redirección. `shellcheck` sin hallazgos.
