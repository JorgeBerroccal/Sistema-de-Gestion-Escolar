#!/bin/sh
# Arranque de la imagen de producción: genera las cachés de Laravel con el entorno real del
# contenedor (config, rutas, vistas, eventos) y luego lanza PHP-FPM (constitución, Principio I).
set -eu

: "${APP_KEY:?APP_KEY es obligatoria: defínela en el .env del servidor}"

php artisan optimize

exec "$@"
