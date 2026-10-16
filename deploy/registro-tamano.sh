#!/usr/bin/env bash
# Añade a /var/log/sge/tamano.log una línea "<fecha UTC> <vmSize>" con el tamaño actual de la VM según el
# servicio de metadatos de Azure. Lo ejecuta cron al arrancar y cada 5 minutos (deploy/cron-sge): deja
# constancia en el propio servidor de que la máquina volvió a B2ats_v2 después de una prueba de carga
# (FR-022a). Fuera de Azure registra "desconocido".
set -euo pipefail
DIR_SCRIPT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=deploy/lib.sh
source "$DIR_SCRIPT/lib.sh"

mkdir -p "$SGE_LOG_DIR"
tamano="$(tamano_vm)"
printf '%s %s\n' "$(date -u +%FT%TZ)" "${tamano:-desconocido}" >> "$SGE_LOG_DIR/tamano.log"
