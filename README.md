# SaaS PHP

SaaS PHP is a Laravel starter kit for building subscription-based products with a modern customer app, a professional Filament admin panel, and a reusable billing foundation.

It combines Laravel, React, Inertia, Filament, Tailwind, Fortify, and Stripe into a starter that is practical enough for real product work while still staying flexible for custom SaaS builds.

## Overview

This starter kit is built around two clear surfaces:

- The customer-facing app for onboarding, pricing, checkout, subscriptions, invoices, and account management
- The Filament control panel for operating the business, managing plans, subscriptions, users, roles, permissions, and settings

The goal is to give you a strong SaaS foundation without forcing one rigid product shape.

## What You Get

### Authentication and account security

- Email and phone-based authentication flows
- Email verification and phone verification
- Magic link login
- Social login support for Google, Microsoft, Yahoo, GitHub, and Twitter
- Two-factor authentication support
- Profile and account settings

### Authorization and user management

- Role and permission system powered by Spatie Permission
- Policy-based authorization across the app
- User impersonation for support workflows
- Filament admin resources for managing users and access

### Billing and subscriptions

- Stripe-powered subscriptions
- Monthly and annual plan pricing
- Trial period support
- Plan upgrades and downgrades
- Billing cycle changes
- Cancel now or cancel at period end
- Resume during grace period
- Invoice history and PDF downloads
- Stripe webhook handling with signature verification
- Database-backed protection against duplicate active subscriptions

### Starter-kit architecture

- Reusable billing services and actions
- Reusable billing UI module under `resources/js/modules/billing`
- Config-based billing enable/disable support
- Optional billing navigation registration
- Clean route separation with `routes/billing.php`

### Admin experience

- Filament admin panel with custom branding and improved visual styling
- Billing resources for plans and customer subscriptions
- Site settings management
- Operational access for roles, permissions, and user administration

### Developer experience

- Laravel 13
- React 19 + TypeScript
- Inertia.js
- Tailwind CSS
- Vite
- Pest / PHPUnit testing
- ESLint, Prettier, and TypeScript checks

## Current Product Scope

SaaS PHP is strongest today as a starter for:

- Single-tenant SaaS products
- Subscription products with Stripe billing
- Internal admin + customer dashboard setups
- Products that need authentication, permissions, settings, and account billing out of the box

It is not yet a full multi-tenant platform with team billing, seat-based billing, or advanced revenue analytics. Those are natural next-phase extensions, but they are not the current core scope.

## Tech Stack

### Backend

- PHP 8.2+
- Laravel 13
- Laravel Fortify
- Filament 3
- Spatie Permission
- Stripe PHP SDK

### Frontend

- React 19
- TypeScript
- Inertia.js
- Tailwind CSS
- Radix UI primitives

### Tooling

- Vite
- Pest / PHPUnit
- ESLint
- Prettier

## Requirements

- Docker Desktop or Docker Engine with Docker Compose
- Composer
- Node.js 18 or newer

Sail is the recommended way to run the project locally. The repository already includes a `compose.yaml` file with:

- `laravel.test`
- `mysql`

## Installation

```bash
git clone https://saasphp.com.git saasphp
cd saasphp

composer install
cp .env.example .env

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed

./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

If you prefer the shorter command form, add the common Sail alias:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

Then you can use `sail up -d`, `sail artisan migrate`, and similar commands.

## Development

### Recommended commands

Start the application containers:

```bash
./vendor/bin/sail up -d
```

Run the app setup commands:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

Start Vite in a separate terminal:

```bash
./vendor/bin/sail npm run dev
```

### Useful individual commands

```bash
./vendor/bin/sail artisan queue:listen --tries=1
./vendor/bin/sail artisan pail
./vendor/bin/sail artisan test
./vendor/bin/sail shell
```

## Billing Setup

Billing is enabled through the application config and environment.

Key environment variables:

```env
BILLING_ENABLED=true
BILLING_NAV_ENABLED=true
BILLING_NAV_SHOW_PRICING=true
BILLING_NAV_SHOW_SUBSCRIPTIONS=true

STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

Seed sample billing plans:

```bash
./vendor/bin/sail artisan db:seed --class=BillingSeeder
```

Customer-facing billing routes:

- `/billing/pricing`
- `/billing/checkout`
- `/subscriptions`
- `/subscriptions/{subscription}`
- `/subscriptions/{subscription}/invoices`

Webhook endpoint:

- `POST /webhooks/stripe`

## Filament Admin Panel

The admin panel is available at:

- `/admin`

Filament is intended for operating the product, not replacing the customer app. It is the right place for:

- user management
- roles and permissions
- site settings
- subscription plans
- customer subscriptions
- operational billing oversight

The customer app is where pricing, checkout, subscriptions, and invoice UX live.

## Default Seeded Users

On a fresh seeded install, the project creates development accounts similar to:

| Email | Password | Role |
| --- | --- | --- |
| `admin@saasphp.com` | `password` | Admin |
| `user1@saasphp.com` | `password` | User |
| `user2@saasphp.com` | `password` | User |

These accounts are for local development only. Change or remove them before production use.

## Project Structure

```text
app/
├── Actions/
│   ├── Billing/
│   └── Fortify/
├── Filament/
├── Http/
├── Models/
├── Policies/
├── Providers/
└── Services/
    └── Billing/

resources/
├── css/
├── js/
│   ├── components/
│   ├── layouts/
│   ├── modules/
│   │   └── billing/
│   └── pages/
└── views/

routes/
├── auth.php
├── billing.php
├── settings.php
└── web.php
```

## Testing

Run the full test suite:

```bash
./vendor/bin/sail artisan test
```

Run focused billing tests:

```bash
./vendor/bin/sail artisan test tests/Feature/BillingTest.php tests/Unit/Models/CustomerSubscriptionTest.php
```

Frontend quality checks:

```bash
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run types
./vendor/bin/sail npm run build
```

## Deployment Notes

Before production deployment:

- configure real Stripe keys and webhook secret
- disable or replace seeded demo users
- review role and permission assignments
- set production mail, queue, cache, and database drivers
- run migrations and build production assets

Example production flow:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Status

The current starter includes a production-minded Phase 1 SaaS foundation:

- hardened authorization model
- Stripe subscription lifecycle support
- plans and pricing data model
- billing webhooks
- pricing and checkout flow
- invoice access
- reusable billing module structure

## Contributing

1. Fork the repository
2. Create a branch
3. Run tests and checks
4. Open a pull request

## Support

Use the repository issues for bug reports, regressions, and feature requests.
