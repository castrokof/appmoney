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

## Módulos

| Ruta | Quién | Qué hace |
|---|---|---|
| `/` | todos | Cobro de hoy: tarjetas con botón **Cobrar**, totales y caja |
| `/atrasados` | todos | Préstamos con cuotas vencidas |
| `/clientes` | todos | CRUD y búsqueda; orden de ruta |
| `/prestamos` | todos | Crear (con vista previa), detalle, cuotas, deshacer pagos, anular |
| `/gastos` | todos | Gastos del cobrador por mes |
| `/bases` | admin, empresa | Entregar base de dinero y ver la caja por cobrador |
| `/resumen` | admin, empresa | Cobrado por día/cobrador, total en la calle, atrasado |
| `/usuarios` | admin, empresa | Crear y desactivar usuarios |

Config: `COBROS_BLOQUEAR_SIN_BASE=true|false` en `.env` (rechazar o solo advertir cuando el préstamo supera la caja).

## Pruebas

```bash
php artisan test
```
