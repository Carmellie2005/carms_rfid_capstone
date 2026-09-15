# SLSU Bontoc Patrol

Laravel-based RFID patrol monitoring, checklist proof, incident reporting, PDF reporting, and PWA support for SLSU Bontoc Campus.

## Local Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

## Tests

```bash
php artisan test
npm run build
```

## Deployments

Render is still kept for testing through `render.yaml`, `Dockerfile`, and `docker/render-start.sh`.

Hostinger preparation files are included separately:

- `.env.hostinger.example`
- `scripts/hostinger-deploy.sh`
- `docs/hostinger-deployment.md`

Read [Hostinger Deployment Prep](docs/hostinger-deployment.md) before moving the production site.
