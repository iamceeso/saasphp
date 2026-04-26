---
title: SaaS PHP
sidebar_position: 1
---

# SaaS PHP

SaaS PHP is a Laravel starter kit for subscription products. It combines a customer-facing React and Inertia app with a Filament admin panel, Stripe billing primitives, account security features, and a database-backed settings system.

This documentation is based on the current code in this repository, not on marketing copy. Where the code differs from the existing README, this docs set follows the implementation in the app.

## Core surfaces

- Customer app built with Inertia and React for onboarding, pricing, checkout, subscriptions, invoices, and account settings
- Filament admin panel at `/admin` for users, roles, permissions, plans, subscriptions, and site settings
- Shared settings layer stored in the `settings` table and loaded dynamically into runtime config
- Stripe-powered billing flows with subscription lifecycle management and webhook processing

## Main features

- Email or phone login
- Optional registration toggle
- Optional email verification
- Optional phone verification
- Optional two-factor authentication
- Magic link login
- Social login for Google, Microsoft, Yahoo, GitHub, and Twitter
- Role and permission management with Spatie Permission and Filament Shield
- User impersonation support in admin workflows
- Subscription plans with monthly and annual pricing
- Free plans and paid Stripe plans
- Plan swaps, billing cycle changes, cancellation, resume, and invoice history
- Database-backed site settings for branding, auth toggles, social providers, email, SMS, and payment credentials

## Current stack

### Backend

- PHP 8.2+
- Laravel 12
- Laravel Fortify
- Laravel Cashier
- Filament 5
- Spatie Permission
- Socialite

### Frontend

- React 19
- TypeScript
- Inertia.js
- Tailwind CSS 4
- Radix UI primitives
- Vite 6

### Tooling

- Pest and PHPUnit
- ESLint
- Prettier
- TypeScript type checking

## Important implementation notes

- Billing routes are only registered when `config('billing.enabled')` is `true`
- Site configuration is seeded from `config/saasphp-data.php` into the database
- Settings override runtime config for Stripe, OAuth providers, and mail transport
- Prices are stored in minor currency units, for example `999` for `$9.99`
- The Stripe webhook route in code is `POST /webhooks/stripe`

## Who this starter is for

SaaS PHP fits best when you want a strong starting point for a single-product SaaS app with one customer account per user, Stripe subscriptions, a separate admin area, and configurable auth and settings features.

It is not currently a full multi-tenant platform with teams, seats, tenant isolation, or advanced revenue analytics.
