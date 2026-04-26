---
title: Billing And Subscriptions
sidebar_position: 6
---

# Billing And Subscriptions

Billing is one of the core pieces of this starter. It uses Stripe for paid subscriptions and also supports free plans through local records only.

## Billing route map

The current route surface is:

- `GET /billing/pricing`
- `GET /billing/checkout`
- `POST /billing/subscribe`
- `POST /webhooks/stripe`
- `GET /subscriptions`
- `GET /subscriptions/{subscription}`
- `POST /subscriptions/{subscription}/swap-plan`
- `POST /subscriptions/{subscription}/change-cycle`
- `POST /subscriptions/{subscription}/cancel`
- `POST /subscriptions/{subscription}/resume`
- `GET /subscriptions/{subscription}/invoices`

The entire `routes/billing.php` file is only loaded when `billing.enabled` is `true`.

## Plan model

Plans are represented by:

- `SubscriptionPlan`
- `PlanPrice`
- `PlanFeature`

Key plan capabilities:

- Monthly and annual intervals
- Active and inactive states
- One `is_most_popular` plan at a time
- `subscribe` or `contact` CTA mode
- Optional contact URL and button text

## Price storage

Amounts are stored in minor units:

- `999` means `9.99`
- `2999` means `29.99`

This applies across plan prices and subscription records.

## Seeded plans

`BillingSeeder` creates three starter plans:

- `starter`
- `professional`
- `enterprise`

It also seeds:

- monthly and annual prices
- trial durations
- feature lists
- a contact CTA for the enterprise plan

## Checkout flow

The pricing controller handles three main billing states:

1. Viewing active plans
2. Entering checkout for a chosen plan and interval
3. Creating a subscription

For paid plans:

- A Stripe customer is created or reused
- A Stripe product and price are created or reused
- The payment method is attached
- The Stripe subscription is created
- The local `customer_subscriptions` record is synchronized

For free plans:

- No Stripe payment method is required
- A local subscription record is created with provider metadata set to `free`

## Subscription lifecycle actions

Current subscription operations include:

- subscribe
- swap plan
- change billing cycle
- cancel immediately or at period end
- resume during grace period

These are implemented through dedicated action classes backed by `SubscriptionService`.

## Subscription state handling

`CustomerSubscription` stores local subscription state, including:

- current plan
- status
- interval
- amount
- current billing period
- trial end
- cancellation timestamps
- metadata

The model treats `trialing` and `active` as active states, and it also understands grace periods after cancellation.

## Duplicate active subscription protection

The codebase explicitly protects the "current subscription slot" for each user by using `current_subscription_key` and normalization logic in `SubscriptionService`.

This helps prevent multiple active local subscriptions for the same customer record.

## Webhooks

Stripe webhooks are processed through `WebhookService`.

Handled event types:

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`
- `customer.subscription.trial_will_end`

Webhook logs are stored and processed idempotently:

- existing Stripe event IDs are reused
- already processed logs are skipped
- failed attempts are tracked

## Invoices

Invoice history is available per subscription page through:

- hosted Stripe invoice URLs
- PDF invoice URLs
- local payload normalization for the React UI

If the subscription provider metadata is `local`, invoice retrieval is skipped.

## Billing configuration checklist

Before enabling paid subscriptions in a real environment:

1. Set valid Stripe publishable, secret, and webhook secret values
2. Confirm your Stripe currency choice
3. Seed or create plans in admin
4. Register the Stripe webhook endpoint at `/webhooks/stripe`
5. Test paid plan checkout and at least one webhook event end-to-end
