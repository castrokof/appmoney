# Cobros (appmoney)

Sistema simple de cobros de préstamos en cuotas. Laravel 12 + Bootstrap 5, mobile-first.
Especificación completa en [`docs/GUIA_SISTEMA_COBROS_SIMPLE.md`](docs/GUIA_SISTEMA_COBROS_SIMPLE.md).

## Despliegue (hosting con PHP 8.2+, MySQL y Composer)

```bash
git clone https://github.com/castrokof/appmoney.git && cd appmoney
composer install --no-dev --optimize-autoloader
cp .env.example .env   # editar: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, ADMIN_*
php artisan key:generate
php artisan migrate --force --seed
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- El document root del subdominio debe apuntar a `public/`.
- Los assets compilados (`public/build`) van en el repo: el hosting **no necesita Node**.
- Tras cambiar CSS/JS en local: `npm install && npm run build` y commitear `public/build`.
- Primer admin: se crea con `php artisan db:seed` usando `ADMIN_EMAIL` y `ADMIN_PASSWORD` del `.env`.
