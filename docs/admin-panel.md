---
title: Admin Panel
sidebar_position: 7
---

# Admin Panel

The admin panel is powered by Filament 5 and mounted at `/admin`.

## What the admin panel is for

The panel is intended for internal operations, not for the customer billing experience. It is the place for:

- user administration
- role and permission management
- subscription plan management
- customer subscription oversight
- site-wide settings

## Panel provider

The admin panel is configured in `App\Providers\Filament\AdminPanelProvider`.

Current defaults include:

- path: `/admin`
- login enabled
- dynamic brand name from `site.name`
- `Manrope` font
- custom theme CSS
- Filament Shield plugin

## Main admin resources

The codebase currently includes these core resources:

- `UserResource`
- `RoleResource`
- `SubscriptionPlanResource`
- `CustomerSubscriptionResource`

There is also a custom page:

- `SiteSettings`

## Access control

Admin access is not open to every authenticated user.

Key rules:

- `User` implements `FilamentUser`
- `User::canAccessPanel()` delegates access checks through policies and gates
- Filament Shield is enabled for resource-level permissions
- Settings page access uses `Gate::allows('modify', Setting::class)`

## Site settings page

The site settings page lives at the admin slug `settings`, which makes the URL:

- `/admin/settings`

It exposes grouped settings for:

- Site info
- Logo and branding
- Locale, timezone, currency, and date formatting
- Social login credentials
- Feature toggles
- Email provider configuration
- SMS provider configuration
- Payment gateway credentials

## Impersonation support

The codebase includes `stechstudio/filament-impersonate`, and the user area provides a leave impersonation route at `/impersonate/leave`.

Sensitive customer settings routes are blocked while impersonating.

## Recommended admin workflow after install

1. Log into `/admin` with the seeded admin account
2. Open the settings page and replace default branding and integration values
3. Review roles and permissions
4. Review seeded plans and prices if billing is enabled
5. Create any real admin users and rotate local development credentials
