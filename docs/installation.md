---
title: Installation
sidebar_position: 2
---

# Installation

This project is a standard Laravel application with a React and Inertia frontend. You can run it with SQLite, MySQL, or PostgreSQL.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 18 or newer
- A supported database

## Clone and install

```bash
git clone https://github.com/iamceeso/saasPHP.git saasphp
cd saasphp

composer install
npm install
cp .env.example .env
php artisan key:generate
```

## Database setup

For SQLite:

```bash
touch database/database.sqlite
```

Then run:

```bash
php artisan migrate
php artisan db:seed
```

The base seeder does two important things:

- Seeds the `settings` table from `config/saasphp-data.php`
- Creates default roles and local development users

If billing is enabled, it also runs `BillingSeeder`.

## Frontend assets

For a production-style build:

```bash
npm run build
```

For local development:

```bash
npm run dev
```

## Local development commands

The repository already defines a few useful Composer scripts:

```bash
composer run dev
composer run new
```

`composer run dev` starts:

- `php artisan serve`
- `php artisan queue:listen --tries=1`
- `php artisan pail --timeout=0`
- `npm run dev`

`composer run new` does the same, but also runs migrations and seeding first.

## Default seeded users

After a fresh seed, these development accounts are created:

| Email | Password | Role |
| --- | --- | --- |
| `admin@saasphp.com` | `password` | `admin` |
| `user1@saasphp.com` | `password` | `user` |
| `user2@saasphp.com` | `password` | `user` |

Change or remove these before using the project outside local development.
