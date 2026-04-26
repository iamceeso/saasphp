---
title: Settings System
sidebar_position: 8
---

# Settings System

One of the most reusable parts of this project is the settings system. It turns application configuration into managed database records instead of leaving everything in files.

## Data model

Settings are stored in the `settings` table through the `Setting` model.

Each record has:

- `key`
- `value`
- `group`
- `type`

The `value` column is encrypted through Eloquent casting.

## Seed source

Default settings are defined in `config/saasphp-data.php` and inserted by `DatabaseSeeder`.

That file acts as the canonical starter definition for:

- default values
- setting groups
- expected data types

## Available groups

### Site settings

- `site.name`
- `site.url`
- `site.description`
- `site.logo`
- `site.theme`
- `site.timezone`
- `site.currency`
- `site.language`
- `site.date_format`
- `site.time_format`

### Social provider settings

- `social.github.*`
- `social.twitter.*`
- `social.google.*`
- `social.yahoo.*`
- `social.microsoft.*`

### Payment gateway settings

- `payments.stripe.*`
- `payments.paystack.*`
- `payments.paddle.*`

The current billing implementation is Stripe-driven, but the settings schema leaves room for future gateways.

### Feature settings

- registration
- email verification
- phone verification
- two-factor authentication
- email sending
- SMS sending
- confirm password behavior
- registration phone or email requirements
- maintenance mode

### Email settings

- selected email client
- sender address
- provider API keys

### SMS settings

- selected SMS client
- sender identity
- Vonage credentials
- Africa's Talking credentials

## Runtime traits

These traits load settings into active runtime config:

- `LoadBillingConfig`
- `LoadOAuthConfig`
- `LoadEmailConfig`
- `LoadSmsConfig`

This means the same admin-managed values can immediately affect application behavior without changing source files.

## Caching behavior

`Setting::getValue()` caches values for one hour using keys like:

- `setting.site.name`
- `setting.features.enable_registration`

When a setting is saved or deleted, the cache for that key is cleared automatically.

## Image updates

The `Setting` model uses `FireImageUpdatedEvent`. In the current implementation that event handling explicitly watches for `site.logo` changes so old logo files can be cleaned up when branding is updated from the admin settings page.

## Why this matters

This settings layer is what makes the starter practical for admin-operated SaaS products. It allows a non-developer administrator to control:

- product branding
- auth behavior
- communications setup
- billing credentials
- locale defaults
