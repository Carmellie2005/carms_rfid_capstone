# Hostinger Deployment Prep

This project can keep Render for testing. Do not delete `render.yaml`, `Dockerfile`, or `docker/render-start.sh` while Render is still used.

## Recommended Hostinger Setup

Use a Hostinger VPS or Laravel VPS template when possible. The app needs SSH, Composer, database access, writable Laravel storage, HTTPS, and the ability to run Artisan commands.

Shared hosting may work only if it allows SSH, Composer, Node/npm or uploaded Vite build files, and setting the document root to Laravel's `public` folder.

## Before Deployment

- Choose the final domain or subdomain.
- Enable SSL/HTTPS before testing camera, GPS, and PWA install.
- Create a production database.
- Decide whether production starts fresh or imports data from Render.
- Prepare SSH access to the Hostinger server.
- Make sure PHP has these extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql` or `pdo_pgsql`, `tokenizer`, `xml`, and `zip`.
- Confirm the web server document root points to the Laravel `public` directory.

## Environment File

Copy the template:

```bash
cp .env.hostinger.example .env
```

Fill in:

- `APP_URL`
- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `WEBPUSH_VAPID_PUBLIC_KEY` and `WEBPUSH_VAPID_PRIVATE_KEY` only if real phone push notifications will be used

Generate the app key on the Hostinger server:

```bash
php artisan key:generate
```

If using web push later:

```bash
php artisan webpush:vapid
```

Never commit the real `.env` file.

## Deploy Commands

From the project directory on the Hostinger server:

```bash
bash scripts/hostinger-deploy.sh
```

For first-time fresh setup with seed accounts/checkpoints:

```bash
bash scripts/hostinger-deploy.sh --seed
```

The script runs Composer install, builds Vite assets when npm is available, clears and rebuilds Laravel caches, runs migrations, links storage, and briefly enables maintenance mode during migration/cache work.

## Manual Fallback

If the script cannot be used, run:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run `php artisan db:seed --force` only for a fresh database or when you intentionally want to refresh the default supervisor, guards, and checkpoints.

## After Deployment Checklist

- Open the public homepage.
- Log in as supervisor.
- Log in as guard.
- Test the ESP32 RFID API URL with the Hostinger domain.
- Test guard phone camera capture.
- Test geolocation permission.
- Submit checklist proof photos.
- Submit incident photos.
- View patrol logs and proof-photo modal.
- Download patrol and incident PDFs.
- Install the PWA on a phone.
- Verify uploaded images load from `/storage/...`.

## Render Notes

Render stays available for testing as long as `render.yaml`, `Dockerfile`, and `docker/render-start.sh` remain in the repo. Hostinger deployment uses `.env.hostinger.example` and `scripts/hostinger-deploy.sh`; Render ignores those files.
