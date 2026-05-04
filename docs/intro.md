# SaaS PHP

A Laravel 13 starter kit for building subscription-based SaaS applications with React, Inertia, Filament, and Stripe.

## Installation

```bash
composer create-project saasphp/saasphp
cd saasphp

cp .env.example .env
php artisan key:generate
php artisan migrate

npm install
npm run build

php artisan serve
```

## Features

* React + Inertia customer app
* Filament admin panel
* Stripe subscriptions (Cashier)
* Role & permission system
* Social login and 2FA
* Database-driven settings

## Documentation

Full documentation is available on the official website:
👉 https://saasphp.com

## Stack

* Laravel 13
* Filament 5
* React 19 + TypeScript
* Tailwind CSS 4
* Vite 6

## License

Proprietary / MIT / etc.
