#!/usr/bin/env bash
set -e

cd /var/www/html

listen_port="${PORT:-${APP_PORT:-80}}"
sed -ri "s/^Listen [0-9]+/Listen ${listen_port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${listen_port}>/" /etc/apache2/sites-available/*.conf

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

php artisan config:clear
php artisan view:clear
php artisan storage:link --force || true
php artisan config:cache
php artisan view:cache

exec apache2-foreground
