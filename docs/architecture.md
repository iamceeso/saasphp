---
title: Architecture
sidebar_position: 4
---

# Architecture

The application is split into two main experiences with shared domain logic underneath them.

## High-level structure

- Customer UI in `resources/js/pages`
- Admin UI in `app/Filament`
- Domain models in `app/Models`
- Business actions in `app/Actions`
- Service-layer helpers in `app/Services`
- Route definitions in `routes/`
- Database schema and seed data in `database/`

## Request surfaces

### Customer app

The customer side is rendered with Inertia and React. Main areas include:

- Welcome page
- Dashboard
- Auth flows
- Settings pages
- Billing pages

These are served through route files like:

- `routes/web.php`
- `routes/auth.php`
- `routes/settings.php`
- `routes/billing.php`

### Admin app

The admin panel is powered by Filament and registered through `App\Providers\Filament\AdminPanelProvider`.

Important defaults:

- Panel path: `/admin`
- Login enabled
- Custom brand name loaded from `site.name`
- Filament Shield plugin enabled

## Service and action pattern

Most important business operations are intentionally separated from controllers:

- `App\Services\Billing\PlanService`
- `App\Services\Billing\SubscriptionService`
- `App\Services\Billing\WebhookService`
- `App\Actions\Billing\SubscribeToPlan`
- `App\Actions\Billing\SwapSubscriptionPlan`
- `App\Actions\Billing\ChangeSubscriptionBillingCycle`
- `App\Actions\Billing\CancelSubscription`
- `App\Actions\Billing\ResumeSubscription`

This keeps controllers focused on validation, authorization, and response formatting.

## Settings-driven runtime behavior

One of the defining patterns in this codebase is that several integrations are not hard-coded in `.env` only. Instead, values are seeded into the database and can be changed through the admin settings page.

That affects:

- Site branding and locale
- Social OAuth credentials
- Mail transport selection
- SMS provider selection
- Stripe billing keys
- Feature toggles like maintenance mode and registration

## Authorization approach

Authorization is layered:

- Fortify handles auth flows
- Policies live in `app/Policies`
- Roles and permissions use Spatie Permission
- Admin permissions are integrated with Filament Shield

Customer-facing sections also use middleware to keep privileged staff accounts out of user-only account areas.

## Current domain boundaries

The currently active and documented product surface is:

- Authentication
- Settings
- Users and roles
- Billing and subscriptions
- Admin operations

There are also marketplace-related models under `app/Models/Marketplace`, but there are no matching route surfaces or docs-level product workflows wired into the current app.
