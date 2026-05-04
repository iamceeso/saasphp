---
title: Installation
sidebar_position: 2
---

# Installation

This project is a standard Laravel application with a React and Inertia frontend. The recommended local development environment is Laravel Sail.

## Requirements

- Docker Desktop or Docker Engine with Docker Compose
- Composer
- Node.js 18 or newer

Sail provides the runtime container and the repository already includes a `compose.yaml` file for local development.

## Create a project

```bash
composer create-project saasphp/saasphp saasphp
cd saasphp

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail npm install
```

The Composer project scripts will:

- copy `.env.example` to `.env` if needed
- generate the application key automatically

The base seeder does two important things:

- Seeds the `settings` table from `config/saasphp-data.php`
- Creates default roles and local development users

If billing is enabled, it also runs `BillingSeeder`.

## Sail command alias

If you want the standard short command, add this alias to your shell:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

After that, `./vendor/bin/sail artisan migrate` becomes `sail artisan migrate`.

## Frontend assets

For a production-style build:

```bash
./vendor/bin/sail npm run build
```

For local development:

```bash
./vendor/bin/sail npm run dev
```

## Local development commands

Start the containers:

```bash
./vendor/bin/sail up -d
```

Stop the containers:

```bash
./vendor/bin/sail down
```

Useful development commands:

- `./vendor/bin/sail artisan migrate`
- `./vendor/bin/sail artisan db:seed`
- `./vendor/bin/sail artisan queue:listen --tries=1`
- `./vendor/bin/sail artisan pail`
- `./vendor/bin/sail artisan test`
- `./vendor/bin/sail npm run dev`
- `./vendor/bin/sail shell`

The documented local workflow uses Sail for the runtime environment.

## Default seeded users

After a fresh seed, these development accounts are created:

| Email | Password | Role |
| --- | --- | --- |
| `admin@saasphp.com` | `password` | `admin` |
| `user1@saasphp.com` | `password` | `user` |
| `user2@saasphp.com` | `password` | `user` |

Change or remove these before using the project outside local development.
