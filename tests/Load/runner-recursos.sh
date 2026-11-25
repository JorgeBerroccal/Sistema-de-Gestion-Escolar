#!/usr/bin/env bash
# Registra CPU y memoria del RUNNER de GitHub Actions durante la rampa de k6 (sesión de 2000 usuarios, enmienda 2.4.0):
# demuestra si el generador de carga fue o no el cuello de botella. Lee /proc, sin dependencias.
#   runner-recursos.sh iniciar <csv>            empieza a muestrear cada 5 s en segundo plano (imprime el PID)
#   runner-recursos.sh resumir <csv> <json>     resume máximos y p95 (CPU %, memoria MB) y la cantidad de vCPU
set -euo pipefail

INTERVALO="${RUNNER_INTERVALO:-5}"

leer_cpu() { awk '/^cpu /{ idle=$5+$6; total=0; for(i=2;i<=NF;i++) total+=$i; print total, idle; exit }' /proc/stat; }

muestrear() {
    local csv="$1" t0 i0 t1 i1 cpu mem_tot mem_disp
    read -r t0 i0 < <(leer_cpu)
    while :; do
        sleep "$INTERVALO"
        read -r t1 i1 < <(leer_cpu)
        cpu="$(awk -v t0="$t0" -v i0="$i0" -v t1="$t1" -v i1="$i1" 'BEGIN { d=t1-t0; v=0; if (d > 0) v=100*(d-(i1-i0))/d; printf "%.1f", v }')"
        t0="$t1"; i0="$i1"
        mem_tot="$(awk '/^MemTotal:/{print int($2/1024)}' /proc/meminfo)"
        mem_disp="$(awk '/^MemAvailable:/{print int($2/1024)}' /proc/meminfo)"
        printf '%s,%s,%s,%s,%s\n' "$(date -u +%FT%TZ)" "$cpu" "$(cut -d' ' -f1 /proc/loadavg)" "$((mem_tot - mem_disp))" "$mem_tot" >> "$csv"
    done
}

# p95 y máximo de la columna $2 del CSV $1
resumen_columna() {
    awk -F, -v c="$2" 'NR>1 && $c != "" { v[++n]=$c+0; if ($c+0>m) m=$c+0 }
        END { if (n==0) { print "0 0"; exit }
              for (i=1;i<=n;i++) for (j=i+1;j<=n;j++) if (v[j]<v[i]) { t=v[i]; v[i]=v[j]; v[j]=t }
              k=int(0.95*n); if (k<1) k=1; printf "%.1f %.1f\n", v[k], m }' "$1"
}

case "${1:-}" in
    _muestrear)
        set +e +o pipefail
        muestrear "$2"
        ;;
    iniciar)
        [[ -n "${2:-}" ]] || { echo "Uso: runner-recursos.sh iniciar <csv>" >&2; exit 2; }
        echo "ts,cpu_pct,load1,mem_usada_mb,mem_total_mb" > "$2"
        nohup "$0" _muestrear "$2" >/dev/null 2>&1 &
        echo $!
        ;;
    resumir)
        [[ -n "${2:-}" && -n "${3:-}" ]] || { echo "Uso: runner-recursos.sh resumir <csv> <json>" >&2; exit 2; }
        read -r cpu_p95 cpu_max < <(resumen_columna "$2" 2)
        read -r mem_p95 mem_max < <(resumen_columna "$2" 4)
        muestras=$(( $(wc -l < "$2") - 1 ))
        cat > "$3" <<JSON
{
  "vcpus": $(nproc),
  "ram_mb": $(awk '/^MemTotal:/{print int($2/1024)}' /proc/meminfo),
  "muestras": $muestras,
  "cpu_pct_p95": $cpu_p95,
  "cpu_pct_max": $cpu_max,
  "mem_usada_mb_p95": $mem_p95,
  "mem_usada_mb_max": $mem_max
}
JSON
        cat "$3"
        ;;
    *)
        echo "Uso: runner-recursos.sh iniciar <csv> | resumir <csv> <json>" >&2
        exit 2
        ;;
esac
