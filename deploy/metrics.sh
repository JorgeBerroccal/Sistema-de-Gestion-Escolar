#!/usr/bin/env bash
# Recolector de CPU, memoria y tamaño de la VM durante la prueba de carga (FR-019) y verificación del
# regreso al tamaño económico (FR-022a). GitHub no tiene acceso al servidor, así que esto se ejecuta aquí.
#   metrics.sh start    empieza a muestrear cada 5 s → /var/log/sge/metricas-AAAAMMDD-HHMM.csv
#   metrics.sh stop     detiene el muestreo, resume máximos/p95 y actualiza meta-servidor.json
#   metrics.sh tamano   imprime el tamaño de la VM (servicio de metadatos de Azure)
#   metrics.sh cierre   la evidencia solo es completa si la VM volvió a Standard_B2ats_v2 (escribe cierre-carga.json)
# Contrato: specs/002-despliegue-ci-staging/contracts/scripts-servidor.md
set -euo pipefail
DIR_SCRIPT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=deploy/lib.sh
source "$DIR_SCRIPT/lib.sh"

INTERVALO="${SGE_METRICS_INTERVAL:-5}"
TAMANO_ECONOMICO="Standard_B2ats_v2"
TARIFA_HORA_CARGA="0.1341"   # USD/h de D2s_v4 en Chile Central (97,92 USD/mes ÷ 730 h)
PID_FILE="$SGE_LOG_DIR/metrics.pid"
ACTUAL_FILE="$SGE_LOG_DIR/metrics.actual"
META_FILE="$SGE_LOG_DIR/meta-servidor.json"

ahora_utc() { date -u +%FT%TZ; }

# MiB desde "123.4MiB", "1.5GiB", "800kB"...
a_mb() {
    awk -v v="$1" 'BEGIN {
        n = v + 0; u = v; sub(/^[0-9.]+/, "", u)
        if (u ~ /^[Gg]/) n *= 1024; else if (u ~ /^[Kk]/) n /= 1024; else if (u !~ /^[Mm]/) n /= (1024 * 1024)
        printf "%.1f", n }'
}

leer_cpu() { awk '/^cpu /{ idle=$5+$6; total=0; for(i=2;i<=NF;i++) total+=$i; print total, idle; exit }' /proc/stat; }

muestrear() { # $1 = archivo CSV
    local csv="$1" t0 i0 t1 i1 cpu load mem_tot mem_disp swap_tot swap_libre
    read -r t0 i0 < <(leer_cpu)
    while :; do
        sleep "$INTERVALO"
        read -r t1 i1 < <(leer_cpu)
        cpu="$(awk -v t0="$t0" -v i0="$i0" -v t1="$t1" -v i1="$i1" 'BEGIN { d=t1-t0; v=0; if (d > 0) v=100*(d-(i1-i0))/d; printf "%.1f", v }')"
        t0="$t1"; i0="$i1"
        load="$(cut -d' ' -f1 /proc/loadavg)"
        mem_tot="$(awk '/^MemTotal:/{print int($2/1024)}' /proc/meminfo)"
        mem_disp="$(awk '/^MemAvailable:/{print int($2/1024)}' /proc/meminfo)"
        swap_tot="$(awk '/^SwapTotal:/{print int($2/1024)}' /proc/meminfo)"
        swap_libre="$(awk '/^SwapFree:/{print int($2/1024)}' /proc/meminfo)"

        local fila ctr servicio cpu_c mem_c
        fila="$(date -u +%FT%TZ),$cpu,$load,$((mem_tot - mem_disp)),$mem_tot,$((swap_tot - swap_libre))"
        local stats
        stats="$(docker stats --no-stream --format '{{.Name}} {{.CPUPerc}} {{.MemUsage}}' 2>/dev/null || true)"
        for servicio in app db redis web; do
            cpu_c=""; mem_c=""
            ctr="$(printf '%s\n' "$stats" | awk -v s="-$servicio-" '!hecho && index($1, s) { print; hecho=1 }')"
            if [[ -n "$ctr" ]]; then
                cpu_c="$(printf '%s' "$ctr" | awk '{gsub("%","",$2); print $2}')"
                mem_c="$(a_mb "$(printf '%s' "$ctr" | awk '{print $3}')")"
            fi
            fila="$fila,$cpu_c,$mem_c"
        done
        printf '%s\n' "$fila" >> "$csv"
    done
}

# percentil 95 y máximo de la columna $2 (numérica) del CSV $1
resumen_columna() {
    awk -F, -v c="$2" 'NR>1 && $c != "" { v[++n]=$c+0; if ($c+0>m) m=$c+0 }
        END { if (n==0) { print "0 0"; exit }
              for (i=1;i<=n;i++) for (j=i+1;j<=n;j++) if (v[j]<v[i]) { t=v[i]; v[i]=v[j]; v[j]=t }
              k=int(0.95*n); if (k<1) k=1; printf "%.1f %.1f\n", v[k], m }' "$1"
}

escribir_meta() { # $1 inicio · $2 fin ("" si sigue en curso) · $3 csv
    local version perfil
    version="$(head -n 1 "$SGE_DIR/VERSION" 2>/dev/null || echo desconocida)"
    perfil="$(grep -E '^PERFIL_TAMANO=' "$SGE_ENV_FILE" 2>/dev/null | cut -d= -f2 || true)"
    cat > "$META_FILE" <<JSON
{
  "vm_size": "$(tamano_vm)",
  "vcpus": $(nproc),
  "ram_mb": $(awk '/^MemTotal:/{print int($2/1024)}' /proc/meminfo),
  "modelo_cpu": "$(awk -F: '/^model name/{gsub(/^ +/,"",$2); print $2; exit}' /proc/cpuinfo)",
  "version_desplegada": "$version",
  "perfil_tamano": "${perfil:-desconocido}",
  "inicio_utc": "$1",
  "fin_utc": "$2",
  "csv": "$3"
}
JSON
}

comando="${1:-}"
case "$comando" in
    _muestrear) # uso interno: proceso de larga duración; un fallo puntual de una lectura no debe matarlo
        set +e +o pipefail
        muestrear "$2"
        ;;
    start)
        mkdir -p "$SGE_LOG_DIR"
        if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
            die "Ya hay un muestreo en curso (PID $(cat "$PID_FILE")); ejecuta primero 'metrics.sh stop'."
        fi
        csv="$SGE_LOG_DIR/metricas-$(date +%Y%m%d-%H%M).csv"
        echo "ts,cpu_pct,load1,mem_usada_mb,mem_total_mb,swap_usada_mb,ctr_app_cpu,ctr_app_mem_mb,ctr_db_cpu,ctr_db_mem_mb,ctr_redis_cpu,ctr_redis_mem_mb,ctr_web_cpu,ctr_web_mem_mb" > "$csv"
        escribir_meta "$(ahora_utc)" "" "$csv"
        ahora_utc > "$SGE_LOG_DIR/ultimo-start"
        nohup "$DIR_SCRIPT/metrics.sh" _muestrear "$csv" >/dev/null 2>&1 &
        echo $! > "$PID_FILE"
        echo "$csv" > "$ACTUAL_FILE"
        log "Muestreo iniciado (PID $(cat "$PID_FILE"), cada ${INTERVALO}s): $csv"
        log "Tamaño de la VM: $(tamano_vm) · ahora lanza el workflow 'carga' en GitHub."
        ;;
    stop)
        [[ -f "$PID_FILE" ]] || die "No hay muestreo en curso (no existe $PID_FILE)."
        pid="$(cat "$PID_FILE")"
        kill "$pid" 2>/dev/null || log "El proceso $pid ya no estaba en marcha (¿el servidor se reinició?)."
        csv="$(cat "$ACTUAL_FILE")"
        fin="$(ahora_utc)"
        inicio="$(cat "$SGE_LOG_DIR/ultimo-start" 2>/dev/null || echo "")"
        escribir_meta "${inicio}" "$fin" "$csv"
        echo "$fin" > "$SGE_LOG_DIR/ultimo-stop"
        rm -f "$PID_FILE"
        muestras=$(( $(wc -l < "$csv") - 1 ))
        log "Muestreo detenido: $muestras muestras en $csv"
        printf '%-26s %10s %10s\n' "métrica" "p95" "máximo"
        for par in "CPU del servidor (%):2" "Carga (load1):3" "Memoria usada (MB):4" "Swap usado (MB):6" \
                   "CPU app (%):7" "Memoria app (MB):8" "CPU db (%):9" "Memoria db (MB):10" "CPU web (%):13" "Memoria web (MB):14"; do
            read -r p95 maximo < <(resumen_columna "$csv" "${par##*:}")
            printf '%-26s %10s %10s\n' "${par%:*}" "$p95" "$maximo"
        done
        log "Metadatos: $META_FILE · descarga: scp sge@<fqdn>:$csv $META_FILE ."
        ;;
    tamano)
        tamano_vm
        ;;
    cierre)
        [[ -f "$SGE_LOG_DIR/ultimo-stop" ]] || die "No hay una sesión detenida: ejecuta antes 'metrics.sh stop'."
        # Muestra fresca: no hay que esperar al siguiente cron para saber en qué tamaño está la VM ahora.
        "$DIR_SCRIPT/registro-tamano.sh"
        ultima="$(tail -n 1 "$SGE_LOG_DIR/tamano.log")"
        fecha_ultima="${ultima%% *}"
        tamano_actual="${ultima#* }"
        parada="$(cat "$SGE_LOG_DIR/ultimo-stop")"
        if [[ "$tamano_actual" == "$TAMANO_ECONOMICO" && ! "$fecha_ultima" < "$parada" ]]; then
            # Muestras (una cada 5 min) del último tramo en tamaño de carga; las horas con la VM liberada no cuentan.
            n_carga="$(awk '$2 ~ /D2s_v4/ { if (prev != "D2") c = 0; c++; prev = "D2"; next } { prev = $2 } END { print c + 0 }' "$SGE_LOG_DIR/tamano.log")"
            horas="$(awk -v n="$n_carga" 'BEGIN { printf "%.2f", n * 5 / 60 }')"
            costo="$(awk -v h="$horas" -v t="$TARIFA_HORA_CARGA" 'BEGIN { printf "%.2f", h * t }')"
            cat > "$SGE_LOG_DIR/cierre-carga.json" <<JSON
{
  "evidencia_completa": true,
  "tamano_actual": "$tamano_actual",
  "ultima_muestra_utc": "$fecha_ultima",
  "fin_de_la_prueba_utc": "$parada",
  "horas_aprox_en_tamano_de_carga": $horas,
  "costo_aprox_usd": $costo
}
JSON
            log "evidencia_completa: true — la VM volvió a $tamano_actual (≈ $horas h en D2s_v4, ≈ $costo USD)."
            log "Escrito $SGE_LOG_DIR/cierre-carga.json: descárgalo junto al CSV y a meta-servidor.json."
        else
            log "EVIDENCIA INCOMPLETA: la VM sigue en '$tamano_actual' (última muestra $fecha_ultima; fin de la prueba $parada)." >&2
            log "Vuelve a B2ats_v2 (Portal: Stop → Size → Start) y repite 'metrics.sh cierre'." >&2
            exit 1
        fi
        ;;
    *)
        echo "Uso: metrics.sh start | stop | tamano | cierre" >&2
        exit 2
        ;;
esac
