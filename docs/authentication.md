---
title: Authentication And Security
sidebar_position: 5
---

# Authentication And Security

Authentication is built on Laravel Fortify, custom controllers, settings-driven feature toggles, and a React and Inertia frontend.

## Supported sign-in methods

- Email and password
- Phone and password
- Magic link login
- Social login
- Two-factor challenge when enabled

## Login behavior

`App\Providers\FortifyServiceProvider` customizes Fortify authentication so users can log in with either:

- `email`
- `phone`

The login form expects:

- `login`
- `password`

The provider resolves a user by matching the `login` value against `users.email` or `users.phone`.

## Registration

Registration is controlled by `features.enable_registration`.

- If enabled, Fortify renders the React registration page
- If disabled, the register view falls back to the login page

## Verification flow

Verification behavior is controlled by database settings:

- `features.enable_email_verification`
- `features.enable_phone_verification`

`EnsureUserIsVerified` checks both email and phone status for protected areas such as the dashboard.

Behavior:

- Sends email verification once per session when needed
- Sends phone verification once per session when needed
- Renders `auth/verify-email` or `auth/verify-phone` until verification is complete

## Two-factor authentication

Two-factor support is available through Fortify and the `TwoFactorAuthenticatable` trait on `User`.

Important note: the `features.enable_two_factor_auth` setting does more than toggle the UI. It also gates the social login routes through the `EnsureTwoFactorEnabled` middleware in the current implementation.

## Magic link login

Magic link routes live under `/magic`:

- `GET /magic/login`
- `POST /magic/send`
- `GET /magic/verify`

This gives you a passwordless login option in addition to standard auth.

## Social login providers

Supported providers:

- Google
- Microsoft
- Yahoo
- GitHub
- Twitter

Routes are registered for:

- `GET /login/{provider}`
- `GET /auth/{provider}/callback`

Provider credentials are loaded dynamically from the `settings` table by `LoadOAuthConfig`.

## Social login rules

The current code enforces a few protective rules:

- The provider must return a usable email address
- The provider email must be considered verified
- Existing local accounts are not auto-linked by matching email
- A social identity is stored on first account creation using `oauth_provider` and `oauth_provider_id`

## Account settings pages

Customer settings routes live under `/settings`:

- `/settings/profile`
- `/settings/password`
- `/settings/appearance`
- `/settings/security`

These routes are protected by:

- `auth`
- `verified`
- `MaintenanceModeEnabled`
- `PreventAdminAccessToUserArea`
- `BlockImpersonatedAccess`

## Impersonation

The app supports impersonation workflows for admin operations. The leave route is:

- `GET /impersonate/leave`

When impersonation is active, access to sensitive customer settings is blocked by middleware.
