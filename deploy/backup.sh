#!/usr/bin/env bash
# Respaldo de la base de datos: mysqldump comprimido, retención de 7 días (FR-028).
#   backup.sh [origen]     origen = diario (por defecto) | manual | pre-<etiqueta>
set -euo pipefail
DIR_SCRIPT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=deploy/lib.sh
source "$DIR_SCRIPT/lib.sh"

origen="${1:-diario}"
[[ "$origen" =~ ^[A-Za-z0-9._-]+$ ]] || die "Origen no válido: '$origen' (solo letras, números, punto, guion)."

cargar_env
servicio_en_marcha db || die "El contenedor db no está en marcha; no hay nada que respaldar."

umask 077
mkdir -p "$SGE_BACKUP_DIR"
archivo="$SGE_BACKUP_DIR/sge-$(date +%Y%m%d-%H%M%S)-$origen.sql.gz"

# La contraseña root solo existe dentro del contenedor db (FR-032); no pasa por la línea de comandos del host.
# Las variables se expanden DENTRO del contenedor, por eso van entre comillas simples.
# shellcheck disable=SC2016
if ! dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --routines --triggers --no-tablespaces "$MYSQL_DATABASE"' \
    | gzip > "$archivo.parcial"; then
    mv -f "$archivo.parcial" "$archivo.fallido" 2>/dev/null || true
    die "mysqldump falló; el archivo parcial quedó en $archivo.fallido"
fi

gzip -t "$archivo.parcial" || die "El respaldo está corrupto (gzip -t falló)."
[[ -s "$archivo.parcial" ]] || die "El respaldo está vacío."
respaldo_tiene_tablas "$archivo.parcial" || die "El respaldo no contiene tablas."
mv "$archivo.parcial" "$archivo"
chmod 600 "$archivo"

# Retención: se eliminan los respaldos con más de 7 días
find "$SGE_BACKUP_DIR" -maxdepth 1 -type f \( -name 'sge-*.sql.gz' -o -name 'sge-*.fallido' \) -mmin +$((7 * 24 * 60)) -delete

log "Respaldo creado: $archivo ($(du -h "$archivo" | cut -f1))"
