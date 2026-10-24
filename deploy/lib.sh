#!/usr/bin/env bash
# Funciones comunes de los scripts de operación (deploy.sh, backup.sh, restore.sh, metrics.sh).
# Se carga con `source`; no se ejecuta directamente. Contrato: contracts/scripts-servidor.md.
#
# Variables de entorno que permiten ensayar los scripts fuera del servidor (T033):
#   SGE_DIR, SGE_ENV_FILE, SGE_BACKUP_DIR, SGE_LOG_DIR, SGE_COMPOSE_PROJECT,
#   SGE_HEALTH_URL, SGE_CURL_OPTS (p. ej. "-k" con certificado autofirmado).

SGE_DIR="${SGE_DIR:-/opt/sge}"
SGE_ENV_FILE="${SGE_ENV_FILE:-$SGE_DIR/.env}"
SGE_BACKUP_DIR="${SGE_BACKUP_DIR:-/var/backups/sge}"
SGE_LOG_DIR="${SGE_LOG_DIR:-/var/log/sge}"
SGE_COMPOSE_FILE="${SGE_COMPOSE_FILE:-$SGE_DIR/docker-compose.prod.yml}"

log() {
    printf '%s %s\n' "$(date '+%F %T')" "$*"
}

die() {
    log "ERROR: $*" >&2
    exit 1
}

# Lee el .env del servidor sin ejecutarlo como shell: solo líneas CLAVE=VALOR (comillas externas opcionales).
cargar_env() {
    [[ -f "$SGE_ENV_FILE" ]] || die "No existe $SGE_ENV_FILE (créalo desde deploy/env.staging.example)."
    local permisos
    permisos="$(stat -c '%a' "$SGE_ENV_FILE")"
    [[ "$permisos" == "600" || "$permisos" == "400" ]] \
        || die "$SGE_ENV_FILE debe tener permisos 600 (tiene $permisos): chmod 600 $SGE_ENV_FILE"

    local linea clave valor
    while IFS= read -r linea || [[ -n "$linea" ]]; do
        [[ "$linea" =~ ^[[:space:]]*(#|$) ]] && continue
        [[ "$linea" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]] || continue
        clave="${BASH_REMATCH[1]}"
        valor="${BASH_REMATCH[2]}"
        if [[ "$valor" =~ ^\"(.*)\"$ || "$valor" =~ ^\'(.*)\'$ ]]; then
            valor="${BASH_REMATCH[1]}"
        fi
        export "$clave=$valor"
    done < "$SGE_ENV_FILE"

    [[ -n "${APP_KEY:-}" && "${APP_KEY}" != *"<"* ]] || die "APP_KEY está vacía o sin reemplazar en $SGE_ENV_FILE."
    [[ -n "${REGISTRO:-}" && "${REGISTRO}" != *"<"* ]] || die "REGISTRO está vacío o sin reemplazar en $SGE_ENV_FILE."
    [[ -n "${FQDN:-}" && "${FQDN}" != *"<"* ]] || die "FQDN está vacío o sin reemplazar en $SGE_ENV_FILE."
    ! grep -v '^[[:space:]]*#' "$SGE_ENV_FILE" | grep -q '<[^>]*>' || die "$SGE_ENV_FILE todavía contiene marcadores <...> sin reemplazar."
}

# Perfiles de tamaño (contracts/variables-entorno.md). Un valor ya definido en el .env tiene prioridad.
aplicar_perfil() {
    local perfil="${PERFIL_TAMANO:-diario}"
    case "$perfil" in
        diario)
            : "${FPM_MAX_CHILDREN:=4}" "${FPM_START_SERVERS:=2}" "${FPM_MIN_SPARE:=1}" "${FPM_MAX_SPARE:=3}"
            : "${MYSQL_BUFFER_POOL:=128M}" "${MYSQL_MAX_CONNECTIONS:=40}" "${REDIS_MAXMEMORY:=64mb}" "${PHP_MEMORY_LIMIT:=128M}"
            ;;
        carga)
            : "${FPM_MAX_CHILDREN:=20}" "${FPM_START_SERVERS:=6}" "${FPM_MIN_SPARE:=4}" "${FPM_MAX_SPARE:=10}"
            : "${MYSQL_BUFFER_POOL:=1G}" "${MYSQL_MAX_CONNECTIONS:=100}" "${REDIS_MAXMEMORY:=256mb}" "${PHP_MEMORY_LIMIT:=256M}"
            ;;
        *) die "PERFIL_TAMANO='$perfil' no es válido (usa 'diario' o 'carga')." ;;
    esac
    export FPM_MAX_CHILDREN FPM_START_SERVERS FPM_MIN_SPARE FPM_MAX_SPARE \
        MYSQL_BUFFER_POOL MYSQL_MAX_CONNECTIONS REDIS_MAXMEMORY PHP_MEMORY_LIMIT
    PERFIL_TAMANO="$perfil"
    export PERFIL_TAMANO
}

# docker compose de la pila de producción
dc() {
    docker compose -p "${SGE_COMPOSE_PROJECT:-sge}" --project-directory "$SGE_DIR" \
        -f "$SGE_COMPOSE_FILE" --env-file "$SGE_ENV_FILE" "$@"
}

servicio_en_marcha() {
    dc ps --status running --services 2>/dev/null | grep -qx "$1"
}

# ¿El volcado comprimido contiene tablas? Cuenta en vez de usar `grep -q`: con `pipefail`, grep -q termina
# en la primera coincidencia y zcat recibe SIGPIPE (141) en volcados grandes, dando un falso «sin tablas».
respaldo_tiene_tablas() {
    local tablas
    tablas="$(zcat "$1" | grep -c 'CREATE TABLE' || true)"
    [[ "${tablas:-0}" -gt 0 ]]
}

# Tamaño de la VM según el servicio de metadatos de Azure (vacío fuera de Azure).
tamano_vm() {
    if [[ -n "${SGE_VMSIZE_FILE:-}" && -f "$SGE_VMSIZE_FILE" ]]; then
        tail -n 1 "$SGE_VMSIZE_FILE"
        return 0
    fi
    curl -fsS --max-time 2 -H 'Metadata:true' --noproxy '*' \
        'http://169.254.169.254/metadata/instance/compute/vmSize?api-version=2021-02-01&format=text' 2>/dev/null || true
}

url_salud() {
    echo "${SGE_HEALTH_URL:-https://${FQDN}/up}"
}

version_servida() {
    # shellcheck disable=SC2086
    curl -fsS --max-time 5 ${SGE_CURL_OPTS:-} "$(url_salud)" 2>/dev/null \
        | sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p'
}

# Espera (hasta $2 segundos, por defecto 60) a que /up responda y, si se indica $1, informe esa versión.
verificar_salud() {
    local esperada="${1:-}" limite="${2:-60}" inicio servida
    inicio="$(date +%s)"
    while :; do
        servida="$(version_servida || true)"
        if [[ -n "$servida" && ( -z "$esperada" || "$servida" == "$esperada" ) ]]; then
            log "Comprobación de salud correcta: $(url_salud) informa version=$servida"
            return 0
        fi
        if (( $(date +%s) - inicio >= limite )); then
            log "La comprobación de salud falló tras ${limite}s: $(url_salud) informa version='${servida:-<sin respuesta>}' (esperada: '${esperada:-cualquiera}')" >&2
            return 1
        fi
        sleep 3
    done
}
