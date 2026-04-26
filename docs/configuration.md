---
title: Configuration
sidebar_position: 3
---

# Configuration

SaaS PHP uses two layers of configuration:

1. Standard Laravel config and environment variables
2. Database-backed settings stored in the `settings` table

The database settings layer is what powers the admin settings page and lets non-developers change behavior without editing files.

## Environment variables

These are the main environment values the current code expects.

### Application and database

Use the standard Laravel values from `.env.example`, especially:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- `DB_DATABASE`
- `DB_HOST`
- `DB_PORT`
- `DB_USERNAME`
- `DB_PASSWORD`

### Billing

```env
BILLING_ENABLED=true
BILLING_NAV_ENABLED=true
BILLING_NAV_SHOW_PRICING=true
BILLING_NAV_SHOW_SUBSCRIPTIONS=true

STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_CURRENCY=USD
```

### Optional service integrations

The runtime values for these services are usually loaded from the database settings page, but the underlying Laravel service keys still map to:

- Stripe
- Mailgun
- Resend
- Postmark
- Vonage

## Billing feature flags

`config/billing.php` defines these flags:

- `billing.enabled`: enables or disables billing route registration entirely
- `billing.navigation.enabled`: toggles billing navigation
- `billing.navigation.show_pricing`: controls pricing links
- `billing.navigation.show_subscriptions`: controls subscription links

If `billing.enabled` is `false`, `routes/billing.php` is not loaded.

## Database-backed settings

The default settings seed comes from `config/saasphp-data.php`.

Settings are grouped into:

- `siteSettings`
- `socialProviders`
- `paymentGateways`
- `featuresSettings`
- `emailSettings`
- `smsSettings`

Examples include:

- `site.name`
- `site.logo`
- `features.enable_registration`
- `social.google.client_id`
- `payments.stripe.secret_key`
- `email.client_name`
- `sms.client_name`

## How settings are applied at runtime

`App\Providers\SettingsServiceProvider` loads settings after the app boots and uses them to:

- Share site data with Inertia
- Set the active locale
- Load Stripe keys into `config('services.stripe.*')`
- Load OAuth provider credentials into `config('services.{provider}.*')`
- Load email provider configuration

## Settings that affect product behavior

These toggles are especially important:

- `features.enable_registration`
- `features.enable_email_verification`
- `features.enable_phone_verification`
- `features.enable_two_factor_auth`
- `features.enable_confirm_password`
- `features.phone_email_at_registration`
- `features.email_sending`
- `features.sms_sending`
- `features.maintenance_mode`

## Recommended setup order

After a clean install, configure the project in this order:

1. Run migrations and seeders
2. Sign in as the seeded admin user
3. Open `/admin/settings`
4. Set `site.name`, `site.url`, locale, timezone, and branding
5. Configure Stripe keys
6. Configure social provider credentials if needed
7. Enable email or SMS sending only after provider credentials are valid
