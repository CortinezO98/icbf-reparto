#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/imports storage/attachments storage/reports storage/logs storage/locks

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Instalando dependencias Composer..."
    composer install --no-interaction --prefer-dist
fi

exec "$@"
