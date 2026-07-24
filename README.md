# SaaS PHP

SaaS PHP is a Laravel starter kit for building subscription-based products with a customer-facing app, a Filament admin panel, and a reusable billing foundation.

Official website: https://saasphp.com

Documentation: `docs/`

## Installation

```bash
composer create-project saasphp/saasphp saasphp
```

## Local Setup

After installation:

```bash
cd saasphp
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The app is available at `http://localhost` by default. Sail maps the web
container to `${APP_PORT:-80}`, so set `APP_PORT` in `.env` if you need a
different host port, for example `APP_PORT=8080`.

For a production-style frontend build instead of the Vite dev server:

```bash
./vendor/bin/sail npm run build
```

## Stack

- PHP 8.3+
- Laravel 13
- Filament 5
- React 19
- Inertia.js
- Tailwind CSS
- Pest / PHPUnit

## Useful Commands

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan pail
./vendor/bin/sail artisan queue:listen --tries=1
```

## Support

Use the repository issues for bug reports and feature requests.
