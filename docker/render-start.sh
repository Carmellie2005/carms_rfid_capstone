#!/usr/bin/env bash
set -e

cd /var/www/html

if [ -n "${PORT:-}" ]; then
    sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

php artisan config:clear
php artisan view:clear

php artisan migrate --force

if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan storage:link || true
php artisan config:cache
php artisan view:cache

exec apache2-foreground
