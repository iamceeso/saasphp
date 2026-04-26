---
title: Project Structure
sidebar_position: 12
---

# Project Structure

This is the practical map of the repository as it exists today.

## Top-level directories

```text
app/
bootstrap/
config/
database/
docs/
lang/
public/
resources/
routes/
storage/
tests/
```

## Backend structure

### `app/Actions`

Contains focused business operations, especially around:

- user creation and Fortify actions
- billing subscription lifecycle changes

### `app/Filament`

Contains admin resources and pages such as:

- users
- roles
- subscription plans
- customer subscriptions
- site settings

### `app/Http`

Contains:

- controllers
- middleware
- request objects

### `app/Models`

Contains the core domain models, especially:

- `User`
- `Setting`
- `SubscriptionPlan`
- `PlanPrice`
- `PlanFeature`
- `CustomerSubscription`
- `BillingEvent`
- `WebhookLog`
- `MagicLink`
- `Role`
- `PhoneCode`

### `app/Policies`

Contains authorization rules for:

- users
- roles
- settings
- plans
- subscriptions

### `app/Providers`

Contains bootstrapping for:

- Filament admin panel
- Fortify
- app-wide settings loading
- auth policies
- events

### `app/Services`

Contains dynamic config loaders and billing services.

## Frontend structure

### `resources/js/pages`

Inertia page components for:

- auth
- settings
- billing
- dashboard
- welcome

### `resources/js/modules/billing`

Reusable billing-specific UI pieces and formatting helpers.

### `resources/js/components`

Shared React UI components and app-shell parts.

### `resources/js/layouts`

Layout wrappers for auth, settings, and the main app shell.

## Route files

- `routes/web.php`
- `routes/auth.php`
- `routes/settings.php`
- `routes/billing.php`
- `routes/console.php`

## Database structure

### `database/migrations`

Includes tables for:

- users
- jobs and cache
- settings
- permissions
- phone codes
- magic links
- billing plans, prices, features, subscriptions, events, and webhooks

### `database/seeders`

Includes:

- `DatabaseSeeder`
- `BillingSeeder`

## Tests

The test suite is split into:

- `tests/Feature`
- `tests/Unit`

This keeps request-level behavior separate from lower-level model and policy checks.
