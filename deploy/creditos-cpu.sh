#!/usr/bin/env bash
# Descarga de Azure Monitor los créditos de CPU de la VM (serie B) durante la prueba de carga y dice si se
# agotaron y en qué minuto la CPU quedó limitada a su base (30 % en B2als_v2).
# Se ejecuta en TU PC con la CLI de Azure iniciada (`az login`), NO en el servidor: el servidor no tiene
# credenciales de Azure y las métricas de créditos son de la plataforma, no del sistema operativo.
#   ./deploy/creditos-cpu.sh <desde-utc> <hasta-utc> [<inicio-rampa-utc>]
#   ./deploy/creditos-cpu.sh 2026-11-05T14:00:00Z 2026-11-05T19:30:00Z 2026-11-05T18:12:00Z
# <desde-utc>: arranque tras el Resize (así se ve la acumulación previa). <hasta-utc>: fin de la prueba
# (`ultimo-stop` en el servidor, o la hora del `metrics.sh stop`). <inicio-rampa-utc>: `ultimo-start` de
# metrics.sh; si se omite, se usa <desde-utc>. Espera ≥ 10 min tras <hasta-utc>: Azure Monitor se retrasa.
# Variables: SGE_RG (rg-sge-staging), SGE_VM (sge-staging), SGE_SALIDA (directorio de salida, "."),
# SGE_UMBRAL_AGOTADO (créditos por debajo de los cuales se considera agotado, 0.5).
# Escribe creditos-cpu-AAAAMMDD-HHMM.csv y creditos-resumen.json. Contrato: contracts/scripts-servidor.md
set -euo pipefail

RG="${SGE_RG:-rg-sge-staging}"
VM="${SGE_VM:-sge-staging}"
SALIDA="${SGE_SALIDA:-.}"
UMBRAL="${SGE_UMBRAL_AGOTADO:-0.5}"

log() { printf '%s [creditos] %s\n' "$(date '+%F %T')" "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }

DESDE="${1:-}"; HASTA="${2:-}"; RAMPA="${3:-$DESDE}"
RE_UTC='^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$'
for t in "$DESDE" "$HASTA" "$RAMPA"; do
    [[ "$t" =~ $RE_UTC ]] || die "Uso: creditos-cpu.sh <desde-utc> <hasta-utc> [<inicio-rampa-utc>]  (formato 2026-11-05T14:00:00Z; recibido '$t')"
done
[[ "$DESDE" < "$HASTA" ]] || die "<desde-utc> debe ser anterior a <hasta-utc>."
[[ ! "$RAMPA" < "$DESDE" && ! "$HASTA" < "$RAMPA" ]] || die "<inicio-rampa-utc> debe estar entre <desde-utc> y <hasta-utc>."
command -v python3 >/dev/null || die "Falta python3."

mkdir -p "$SALIDA"
JSON="$(mktemp)"
trap 'rm -f "$JSON"' EXIT

if [[ -n "${SGE_AZ_JSON:-}" ]]; then   # ensayo local sin Azure: usa una respuesta guardada
    cp "$SGE_AZ_JSON" "$JSON"
else
    command -v az >/dev/null || die "Falta la CLI de Azure (az) o no has iniciado sesión: az login."
    ID="$(az vm show -g "$RG" -n "$VM" --query id -o tsv)" || die "No se encontró la VM $VM en $RG."
    az monitor metrics list --resource "$ID" \
        --metric "CPU Credits Remaining" "CPU Credits Consumed" "Percentage CPU" \
        --start-time "$DESDE" --end-time "$HASTA" --interval PT1M --aggregation Average \
        -o json > "$JSON"
fi

SUFIJO="$(date -u +%Y%m%d-%H%M)"
CSV="$SALIDA/creditos-cpu-$SUFIJO.csv"
RESUMEN="$SALIDA/creditos-resumen.json"

python3 -I - "$JSON" "$CSV" "$RESUMEN" "$DESDE" "$HASTA" "$RAMPA" "$UMBRAL" "$VM" <<'PY'
import json, sys
from datetime import datetime, timezone

ruta, csv_ruta, resumen_ruta, desde, hasta, rampa, umbral, vm = sys.argv[1:9]
umbral = float(umbral)
fmt = "%Y-%m-%dT%H:%M:%SZ"

def ts(s):  # Azure devuelve 2026-11-05T14:00:00Z o con fracciones
    return datetime.fromisoformat(s.replace("Z", "+00:00")).astimezone(timezone.utc).replace(microsecond=0)

series = {}
for m in json.load(open(ruta)).get("value", []):
    nombre = m["name"]["value"]
    for s in m.get("timeseries", []):
        for p in s.get("data", []):
            if p.get("average") is not None:
                series.setdefault(ts(p["timeStamp"]), {})[nombre] = p["average"]

if not any("CPU Credits Remaining" in v for v in series.values()):
    sys.exit("Azure Monitor no devolvió 'CPU Credits Remaining' para ese rango (¿faltan ≥ 10 min de retraso, la VM estaba liberada, o la métrica no existe para este tamaño?).")

filas = sorted(series.items())
with open(csv_ruta, "w") as f:
    f.write("ts,cpu_credits_remaining,cpu_credits_consumed,percentage_cpu\n")
    for t, v in filas:
        f.write("%s,%s,%s,%s\n" % (t.strftime(fmt), *(v.get(k, "") for k in ("CPU Credits Remaining", "CPU Credits Consumed", "Percentage CPU"))))

rem = [(t, v["CPU Credits Remaining"]) for t, v in filas if "CPU Credits Remaining" in v]
t_rampa = ts(rampa)
t_hasta = ts(hasta)
antes = [(t, c) for t, c in rem if t <= t_rampa]
en_prueba = [(t, c) for t, c in rem if t >= t_rampa]
minimo = min(en_prueba or rem, key=lambda x: x[1])
agotados = [(t, c) for t, c in en_prueba if c <= umbral]
primer = agotados[0][0] if agotados else None

r = {
    "vm": vm,
    "desde_utc": desde, "hasta_utc": hasta, "inicio_rampa_utc": rampa,
    "muestras_de_1_min": len(rem),
    "creditos_al_inicio_de_la_observacion": rem[0][1],
    "creditos_al_iniciar_la_rampa": (antes[-1][1] if antes else None),
    "creditos_minimos_durante_la_prueba": minimo[1],
    "minimo_alcanzado_utc": minimo[0].strftime(fmt),
    "creditos_al_final": rem[-1][1],
    "umbral_de_agotamiento": umbral,
    "creditos_agotados": bool(agotados),
    "primer_minuto_agotado_utc": primer.strftime(fmt) if primer else None,
    "minutos_desde_el_inicio_de_la_rampa": (round((primer - t_rampa).total_seconds() / 60) if primer else None),
    "minutos_con_cpu_limitada": len(agotados),
    "duracion_de_la_prueba_min": round((t_hasta - t_rampa).total_seconds() / 60),
    "csv": csv_ruta,
}
json.dump(r, open(resumen_ruta, "w"), indent=2, ensure_ascii=False)
print(json.dumps(r, indent=2, ensure_ascii=False))
if r["creditos_agotados"]:
    print("\nLOS CRÉDITOS SE AGOTARON: la CPU quedó limitada a su base desde el minuto %d de la rampa (%s), "
          "durante ≈ %d min. Registra la prueba igual e indica ese minuto en el análisis." %
          (r["minutos_desde_el_inicio_de_la_rampa"], r["primer_minuto_agotado_utc"], r["minutos_con_cpu_limitada"]))
else:
    print("\nLos créditos NO llegaron a cero (mínimo %.1f a las %s UTC): la CPU no estuvo limitada." % (minimo[1], r["minimo_alcanzado_utc"]))
PY
log "CSV: $CSV · resumen: $RESUMEN"
