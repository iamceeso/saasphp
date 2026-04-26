---
title: Frontend And UI
sidebar_position: 9
---

# Frontend And UI

The customer-facing application uses React, TypeScript, Inertia, and Tailwind CSS.

## Entry points

Important frontend entry files include:

- `resources/js/app.tsx`
- `resources/js/ssr.jsx`
- `resources/views/app.blade.php`

## Main page groups

### Public pages

- `welcome`

### Auth pages

- login
- register
- forgot password
- reset password
- confirm password
- verify email
- verify phone
- two-factor challenge
- magic login
- maintenance

### Customer pages

- dashboard
- profile settings
- password settings
- appearance settings
- security settings
- pricing
- checkout
- subscriptions
- subscription detail
- invoices

## Layouts and UI components

Important shared frontend areas:

- `resources/js/layouts`
- `resources/js/components`
- `resources/js/modules/billing`

The billing UI is intentionally grouped under its own module so the pricing and subscription surface can be reused or evolved with less coupling to the rest of the app shell.

## Navigation behavior

Billing navigation visibility is controlled by billing config flags:

- `billing.navigation.enabled`
- `billing.navigation.show_pricing`
- `billing.navigation.show_subscriptions`

## Appearance settings

The settings area includes an appearance page, and site-wide branding data such as name, logo, theme, locale, currency, and formatting are shared from the backend through Inertia.

## Frontend developer commands

```bash
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
./vendor/bin/sail npm run build:ssr
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run format
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types
```

## Styling stack

The repository uses:

- Tailwind CSS 4
- utility classes in React components
- shared UI primitives under `resources/js/components/ui`
- custom admin theme CSS for Filament
