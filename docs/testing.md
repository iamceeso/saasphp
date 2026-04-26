---
title: Testing
sidebar_position: 10
---

# Testing

The codebase includes both feature and unit tests, with Pest and PHPUnit configured in a standard Laravel way.

## Run the test suite

```bash
php artisan test
./vendor/bin/pest
```

## Current test coverage areas

The repository already includes tests for:

- dashboard access
- billing routes and free-plan subscriptions
- Stripe webhook route behavior
- social login behavior
- phone verification
- magic links
- authentication flows
- password reset and password confirmation
- user profile and password settings
- site settings behavior
- appearance settings
- role and policy behavior
- billing-related model behavior

## Useful frontend checks

```bash
npm run lint
npm run types
npm run format:check
```

## Testing notes for billing

The billing code includes a test-only fallback path for subscriptions in the pricing controller when the app is running in the `testing` environment. This keeps subscription tests deterministic without requiring a live Stripe payment method.

## Recommended additions

If you extend the starter for production use, add tests around:

- your custom onboarding logic
- permission-sensitive admin workflows
- real webhook edge cases
- plan migration rules
- any tenant or team logic you introduce
