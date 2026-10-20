#!/usr/bin/env bash
# Despliegue por SSH de una versión etiquetada (imágenes ya publicadas en GHCR). El servidor nunca
# construye imágenes (FR-007). Volver atrás es desplegar la etiqueta anterior (FR-012).
#   deploy.sh <etiqueta>      p. ej. v0.2.0
#   deploy.sh --reconfigurar  recrea los contenedores con el perfil de tamaño actual del .env
#   deploy.sh --estado        versión activa, tamaño de VM, contenedores y vencimiento del certificado
# Contrato: specs/002-despliegue-ci-staging/contracts/scripts-servidor.md
set -euo pipefail
DIR_SCRIPT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=deploy/lib.sh
source "$DIR_SCRIPT/lib.sh"

uso() {
    echo "Uso: deploy.sh <etiqueta> | --reconfigurar | --estado" >&2
    exit 2
}

[[ $# -eq 1 ]] || uso
modo="$1"

version_activa() {
    if [[ -f "$SGE_DIR/VERSION" ]]; then
        head -n 1 "$SGE_DIR/VERSION"
    else
        echo "${SGE_VERSION:-ninguna}"
    fi
}

avisar_si_tamano_no_coincide() {
    local tamano
    tamano="$(tamano_vm)"
    [[ -n "$tamano" ]] || return 0
    log "Tamaño de la máquina: $tamano · perfil: $PERFIL_TAMANO"
    case "$tamano:$PERFIL_TAMANO" in
        *B2als_v2*:diario)
            log "AVISO: la VM está en B2als_v2 (38,40 USD/mes) con perfil 'diario'. Si no es una sesión de carga, vuelve a B2ats_v2." >&2 ;;
        *B2ats_v2*:carga)
            log "AVISO: perfil 'carga' en B2ats_v2 (1 GB): la pila no cabe en memoria. Cambia primero la VM a B2als_v2 (4 GB)." >&2 ;;
    esac
}

registrar_despliegue() { # $1 etiqueta · $2 resultado · $3 segundos
    mkdir -p "$SGE_LOG_DIR"
    printf '%s | %s | %s | %s | perfil=%s | %s | %ss\n' \
        "$(date '+%F %T')" "$1" "$(git -C "$SGE_DIR" rev-parse --short HEAD 2>/dev/null || echo '-')" \
        "$(tamano_vm || true)" "${PERFIL_TAMANO:-?}" "$2" "$3" >> "$SGE_LOG_DIR/despliegues.log"
}

escribir_version_en_env() { # $1 etiqueta
    if grep -q '^SGE_VERSION=' "$SGE_ENV_FILE"; then
        sed -i "s|^SGE_VERSION=.*|SGE_VERSION=$1|" "$SGE_ENV_FILE"
    else
        printf 'SGE_VERSION=%s\n' "$1" >> "$SGE_ENV_FILE"
    fi
}

# --- --estado -----------------------------------------------------------------------------------
if [[ "$modo" == "--estado" ]]; then
    cargar_env
    aplicar_perfil
    echo "Versión activa:  $(version_activa)"
    echo "Servida por /up: $(version_servida || true)"
    echo "Tamaño de VM:    $(tamano_vm || true)  (perfil: $PERFIL_TAMANO)"
    echo "Contenedores:"
    dc ps --format 'table {{.Service}}\t{{.Status}}\t{{.Image}}'
    host_cert="${SGE_CERT_HOST:-$FQDN}"
    vence="$(echo | openssl s_client -connect "${host_cert}:443" -servername "$FQDN" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2 || true)"
    if [[ -n "$vence" ]]; then
        dias=$(( ( $(date -d "$vence" +%s) - $(date +%s) ) / 86400 ))
        echo "Certificado:     vence $vence ($dias días)"
    else
        echo "Certificado:     no se pudo leer"
    fi
    exit 0
fi

# --- --reconfigurar ----------------------------------------------------------------------------
if [[ "$modo" == "--reconfigurar" ]]; then
    cargar_env
    aplicar_perfil
    [[ -n "${SGE_VERSION:-}" ]] || die "SGE_VERSION no está definida; despliega primero una etiqueta."
    avisar_si_tamano_no_coincide
    inicio="$(date +%s)"
    log "Reconfigurando con el perfil '$PERFIL_TAMANO' (versión $SGE_VERSION)..."
    dc up -d --wait db redis
    dc up -d app web
    verificar_salud "$SGE_VERSION" 90 || die "Tras reconfigurar, /up no informa la versión $SGE_VERSION."
    registrar_despliegue "$SGE_VERSION" "reconfigurado" "$(( $(date +%s) - inicio ))"
    log "Reconfigurado."
    exit 0
fi

# --- despliegue de una etiqueta --------------------------------------------------------------------
etiqueta="$modo"
[[ "$etiqueta" =~ ^v[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9._-]+)?$ ]] || die "Etiqueta no válida: '$etiqueta' (formato vX.Y.Z)."

# 1) Sincronizar el repositorio de operación con la etiqueta (así compose, nginx y scripts coinciden con
#    la versión) y volver a ejecutar esta misma versión del script.
if [[ -z "${SGE_DEPLOY_REEXEC:-}" && -z "${SGE_SKIP_GIT:-}" ]]; then
    log "Sincronizando $SGE_DIR con la etiqueta $etiqueta..."
    git -C "$SGE_DIR" fetch --tags --prune --force origin
    git -C "$SGE_DIR" rev-parse -q --verify "refs/tags/${etiqueta}^{commit}" >/dev/null \
        || die "La etiqueta $etiqueta no existe en Git (¿ya la subiste con git push origin $etiqueta?)."
    git -C "$SGE_DIR" checkout -q --detach "refs/tags/$etiqueta"
    SGE_DEPLOY_REEXEC=1 exec "$DIR_SCRIPT/deploy.sh" "$etiqueta"
fi

inicio="$(date +%s)"
cargar_env
aplicar_perfil
anterior="$(version_activa)"
log "Desplegando $etiqueta (activa: $anterior · perfil: $PERFIL_TAMANO)"
avisar_si_tamano_no_coincide

# 2) Las imágenes de la etiqueta deben existir antes de tocar nada
for imagen in "$REGISTRO/sge-app:$etiqueta" "$REGISTRO/sge-web:$etiqueta"; do
    docker manifest inspect "$imagen" >/dev/null 2>&1 || docker image inspect "$imagen" >/dev/null 2>&1 \
        || die "No existe la imagen $imagen (¿terminó el workflow 'release' y el paquete es público?)."
done

fallo() { # $1 mensaje
    registrar_despliegue "$etiqueta" "FALLO" "$(( $(date +%s) - inicio ))"
    log "ERROR: $1" >&2
    if [[ "$anterior" == "ninguna" ]]; then
        log "No hay una versión anterior activa (primer despliegue): corrige la causa y repite ./deploy/deploy.sh $etiqueta" >&2
    else
        log "La versión activa sigue registrada como: $anterior" >&2
        log "Para volver atrás:  ./deploy/deploy.sh $anterior" >&2
    fi
    [[ -z "${respaldo_previo:-}" ]] || log "Respaldo previo a este despliegue: $respaldo_previo (restaurar: ./deploy/restore.sh <archivo>)" >&2
    exit 1
}

# 3) Respaldo previo (si ya hay base de datos en marcha)
respaldo_previo=""
if servicio_en_marcha db; then
    "$DIR_SCRIPT/backup.sh" "pre-$etiqueta"
    respaldo_previo="$(find "$SGE_BACKUP_DIR" -maxdepth 1 -name "sge-*-pre-$etiqueta.sql.gz" -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -n 1 | cut -d' ' -f2- || true)"
else
    log "Primer despliegue (db no está en marcha): no hay respaldo previo."
fi

# 4) Descargar imágenes y levantar la base de datos y Redis (la versión se pasa por entorno)
export SGE_VERSION="$etiqueta"
log "Descargando imágenes..."
# SGE_SKIP_PULL=1 solo para ensayos locales con imágenes que no están en un registro
[[ -n "${SGE_SKIP_PULL:-}" ]] || dc pull app web
dc up -d --wait db redis

# 5) Migrar con la imagen nueva ANTES de recrear app/web: si la migración falla, siguen sirviendo los
#    contenedores anteriores y SGE_VERSION del .env no cambia.
log "Aplicando migraciones..."
dc run --rm --no-deps app php artisan migrate --force || fallo "La migración falló (la base puede haber quedado parcialmente modificada)."

# 6) Recrear app/web (el entrypoint ejecuta php artisan optimize) y verificar la versión servida
log "Recreando app y web..."
dc up -d app web || fallo "No se pudieron levantar app y web."
verificar_salud "$etiqueta" 90 || fallo "La comprobación de salud no informa la versión $etiqueta."

# 7) Solo ahora la versión queda registrada como activa
escribir_version_en_env "$etiqueta"
echo "$etiqueta" > "$SGE_DIR/VERSION"
duracion="$(( $(date +%s) - inicio ))"
registrar_despliegue "$etiqueta" "OK" "$duracion"
log "Desplegada $etiqueta en ${duracion}s."
