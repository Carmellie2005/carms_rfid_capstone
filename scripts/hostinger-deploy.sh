#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "Missing .env. Copy .env.hostinger.example to .env and fill in real Hostinger values first."
    exit 1
fi

seed_database=false

for argument in "$@"; do
    case "$argument" in
        --seed)
            seed_database=true
            ;;
        *)
            echo "Unknown option: $argument"
            echo "Usage: bash scripts/hostinger-deploy.sh [--seed]"
            exit 1
            ;;
    esac
done

maintenance_started=false

finish() {
    if [ "$maintenance_started" = true ]; then
        php artisan up || true
    fi
}

trap finish EXIT

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
    npm ci
    npm run build
else
    echo "npm was not found. Build assets locally and upload public/build before deploying."
fi

php artisan optimize:clear

if php artisan down --render="errors::503" >/dev/null 2>&1; then
    maintenance_started=true
fi

php artisan migrate --force

if [ "$seed_database" = true ] || [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
maintenance_started=false

echo "Hostinger deployment tasks completed."
