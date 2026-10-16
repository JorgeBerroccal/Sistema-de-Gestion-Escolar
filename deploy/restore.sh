#!/usr/bin/env bash
# Restaura la base de datos desde un respaldo (FR-029). El sitio no responde mientras dura.
#   restore.sh <archivo.sql.gz>
# Pide escribir RESTAURAR (o definir SGE_RESTORE_CONFIRM=RESTAURAR en ensayos no interactivos).
set -euo pipefail
DIR_SCRIPT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=deploy/lib.sh
source "$DIR_SCRIPT/lib.sh"

archivo="${1:-}"
[[ -n "$archivo" ]] || die "Uso: restore.sh <archivo.sql.gz>   (respaldos en $SGE_BACKUP_DIR)"
[[ -f "$archivo" ]] || die "No existe el archivo: $archivo"
gzip -t "$archivo" || die "El archivo no es un gzip válido."

cargar_env
aplicar_perfil

echo "Se va a REEMPLAZAR por completo la base de datos con: $archivo"
echo "La aplicación quedará fuera de servicio durante la restauración."
confirmacion="${SGE_RESTORE_CONFIRM:-}"
if [[ -z "$confirmacion" ]]; then
    read -r -p "Escribe RESTAURAR para continuar: " confirmacion
fi
[[ "$confirmacion" == "RESTAURAR" ]] || die "Restauración cancelada."

inicio="$(date +%s)"
version_activa="${SGE_VERSION:-}"
[[ -n "$version_activa" ]] || die "SGE_VERSION no está definida en $SGE_ENV_FILE."

log "Deteniendo app y web..."
dc stop app web
log "Levantando db y redis..."
dc up -d --wait db redis

log "Recreando la base de datos..."
# Las variables ($MYSQL_*) se expanden DENTRO del contenedor db: comillas simples intencionales.
# shellcheck disable=SC2016
dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
log "Cargando el respaldo..."
# shellcheck disable=SC2016
zcat "$archivo" | dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"'

# Las sesiones y cachés de Redis podrían apuntar a datos que ya no existen
log "Vaciando Redis (sesiones y caché)..."
dc exec -T redis redis-cli flushall >/dev/null

log "Levantando app y web (versión $version_activa)..."
dc up -d app web
verificar_salud "$version_activa" 90 || die "Tras restaurar, /up no informa la versión $version_activa."

# shellcheck disable=SC2016
usuarios="$(dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "SELECT COUNT(*) FROM usuarios" "$MYSQL_DATABASE"' | tr -d '[:space:]')"
log "Restauración completada en $(( $(date +%s) - inicio )) s. Usuarios en la base: $usuarios"
