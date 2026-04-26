---
title: Deployment
sidebar_position: 11
---

# Deployment

This project deploys like a normal Laravel application with a separate frontend build step. Laravel Sail is the recommended local development environment, but it is not required in production.

## Production checklist

1. Set a real `APP_ENV`, `APP_URL`, database connection, cache store, and queue configuration
2. Set `APP_KEY`
3. Run migrations
4. Seed only the data you actually want in production
5. Build frontend assets with `npm run build`
6. Configure your queue worker
7. Configure Stripe webhook delivery to `POST /webhooks/stripe`
8. Replace seeded development users and passwords

## Services to verify before go-live

- Stripe keys and webhook secret
- outbound mail provider
- SMS provider if phone verification is enabled
- social OAuth credentials if social login is enabled
- storage and public asset serving for logo uploads

## Feature flags to review

Before production, explicitly review:

- `BILLING_ENABLED`
- `BILLING_NAV_ENABLED`
- `BILLING_NAV_SHOW_PRICING`
- `BILLING_NAV_SHOW_SUBSCRIPTIONS`
- `features.enable_registration`
- `features.enable_email_verification`
- `features.enable_phone_verification`
- `features.enable_two_factor_auth`
- `features.maintenance_mode`

## Queue and background work

The local development workflow runs `php artisan queue:listen --tries=1`. In production, use a supervised queue worker appropriate for your hosting platform.

## Mail and SMS safety

The code intentionally allows email and SMS sending to be disabled through settings. Keep those off until provider credentials and sender identities are validated.

## Stripe production note

The paid subscription flow depends on valid Stripe product, price, customer, payment method, and webhook state. Do at least one real end-to-end checkout and renewal simulation in Stripe test mode before switching live keys.
