<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use Carbon\Carbon;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class SubscriptionService
{
    private ?StripeClient $stripe = null;

    private function getStripeClient(): StripeClient
    {
        if ($this->stripe === null) {
            $secret = config('services.stripe.secret');
            if (!$secret) {
                throw new \InvalidArgumentException(
                    'Stripe secret key is not configured. Please set STRIPE_SECRET_KEY in your .env file.'
                );
            }
            $this->stripe = new StripeClient($secret);
        }
        return $this->stripe;
    }

    public function subscribe(
        User $user,
        SubscriptionPlan $plan,
        string $interval = 'monthly',
        ?string $paymentMethod = null
    ): CustomerSubscription {
        $price = $plan->prices()
            ->where('interval', $interval)
            ->where('is_active', true)
            ->firstOrFail();

        $stripeCustomerId = $this->getOrCreateStripeCustomer($user);
        $stripePriceId = $this->getOrCreateStripePrice($plan, $price);

        if ($paymentMethod) {
            try {
                $this->getStripeClient()->paymentMethods->attach($paymentMethod, [
                    'customer' => $stripeCustomerId,
                ]);
            } catch (ApiErrorException $e) {
                if (!str_contains(strtolower($e->getMessage()), 'already')) {
                    throw $e;
                }
            }

            $this->getStripeClient()->customers->update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod,
                ],
            ]);
        }

        $payload = [
            'customer' => $stripeCustomerId,
            'items' => [
                [
                    'price' => $stripePriceId,
                    'quantity' => 1,
                ],
            ],
            'collection_method' => 'charge_automatically',
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ];

        if ($paymentMethod) {
            $payload['default_payment_method'] = $paymentMethod;
        }

        if ($price->trial_days > 0) {
            $payload['trial_period_days'] = $price->trial_days;
        }

        $subscription = $this->getStripeClient()->subscriptions->create($payload);

        return $this->syncSubscriptionToDB($user, $plan, $subscription, $interval);
    }

    public function swapPlan(
        CustomerSubscription $subscription,
        SubscriptionPlan $newPlan,
        string $interval = 'monthly',
        bool $prorateCosts = true
    ): CustomerSubscription {
        $newPrice = $newPlan->prices()
            ->where('interval', $interval)
            ->where('is_active', true)
            ->firstOrFail();

        $provider = data_get($subscription->metadata, 'provider');

        if ($provider === 'local') {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'interval' => $interval,
                'amount' => $newPrice->amount,
            ]);

            return $subscription->refresh();
        }

        $stripePriceId = $this->getOrCreateStripePrice($newPlan, $newPrice);
        $stripeSubscription = $this->getStripeClient()->subscriptions->retrieve(
            $subscription->stripe_subscription_id
        );
        $stripeItemId = data_get($stripeSubscription, 'items.data.0.id');

        if (!$stripeItemId) {
            throw new \RuntimeException('Unable to determine current Stripe subscription item.');
        }

        $updatedStripeSubscription = $this->getStripeClient()->subscriptions->update(
            $subscription->stripe_subscription_id,
            [
                'items' => [
                    [
                        'id' => $stripeItemId,
                        'price' => $stripePriceId,
                    ],
                ],
                'proration_behavior' => $prorateCosts ? 'create_prorations' : 'none',
                'expand' => ['latest_invoice.payment_intent'],
            ]
        );

        $subscription->update(array_merge(
            ['plan_id' => $newPlan->id],
            $this->mapStripeSubscription($updatedStripeSubscription, $newPlan, $interval)
        ));

        return $subscription->refresh();
    }

    public function cancel(CustomerSubscription $subscription, bool $immediately = false): void
    {
        if ($immediately) {
            $this->getStripeClient()->subscriptions->cancel($subscription->stripe_subscription_id, [
                'invoice_now' => false,
            ]);
        } else {
            $this->getStripeClient()->subscriptions->update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    public function resume(CustomerSubscription $subscription): CustomerSubscription
    {
        $this->getStripeClient()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        $subscription->update([
            'status' => 'active',
            'canceled_at' => null,
        ]);

        return $subscription->refresh();
    }

    public function changeBillingCycle(
        CustomerSubscription $subscription,
        string $newInterval
    ): CustomerSubscription {
        $currentPlan = $subscription->plan;
        $newPrice = $currentPlan->prices()
            ->where('interval', $newInterval)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->swapPlan($subscription, $currentPlan, $newInterval);
    }

    public function getStripeSubscription(CustomerSubscription $subscription)
    {
        return $this->getStripeClient()->subscriptions->retrieve($subscription->stripe_subscription_id, [
            'expand' => ['customer', 'latest_invoice.payment_intent'],
        ]);
    }

    public function getStripeInvoices(CustomerSubscription $subscription, int $limit = 24): array
    {
        $invoices = $this->getStripeClient()->invoices->all([
            'customer' => $subscription->stripe_customer_id,
            'subscription' => $subscription->stripe_subscription_id,
            'limit' => $limit,
        ]);

        return $invoices->data ?? [];
    }

    public function getUpcomingStripeInvoice(CustomerSubscription $subscription): ?object
    {
        try {
            return $this->getStripeClient()->invoices->upcoming([
                'customer' => $subscription->stripe_customer_id,
                'subscription' => $subscription->stripe_subscription_id,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getOrCreateStripeCustomer(User $user): string
    {
        if ($user->stripe_id) {
            return $user->stripe_id;
        }

        $customer = $this->getStripeClient()->customers->create([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        $user->update(['stripe_id' => $customer->id]);

        return $customer->id;
    }

    private function syncSubscriptionToDB(
        User $user,
        SubscriptionPlan $plan,
        $stripeSubscription,
        string $interval
    ): CustomerSubscription {
        $existingSubscription = CustomerSubscription::where(
            'stripe_subscription_id',
            $stripeSubscription->id
        )->first();

        if ($existingSubscription) {
            $existingSubscription->update($this->mapStripeSubscription($stripeSubscription, $plan, $interval));
            return $existingSubscription;
        }

        return CustomerSubscription::create(
            array_merge(
                [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
                $this->mapStripeSubscription($stripeSubscription, $plan, $interval)
            )
        );
    }

    private function mapStripeSubscription($stripeSubscription, SubscriptionPlan $plan, string $interval): array
    {
        $price = $plan->prices()
            ->where('interval', $interval)
            ->first();

        return [
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'status' => $stripeSubscription->status,
            'interval' => $interval,
            'amount' => $price->amount,
            'current_period_start' => Carbon::createFromTimestamp((int) $stripeSubscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp((int) $stripeSubscription->current_period_end),
            'trial_ends_at' => $stripeSubscription->trial_end
                ? Carbon::createFromTimestamp((int) $stripeSubscription->trial_end)
                : null,
            'metadata' => [
                'provider' => 'stripe',
                'currency' => config('services.stripe.currency', 'USD'),
            ],
        ];
    }

    private function getOrCreateStripePrice(SubscriptionPlan $plan, PlanPrice $price): string
    {
        if ($price->stripe_price_id) {
            return $price->stripe_price_id;
        }

        $productId = $this->getOrCreateStripeProduct($plan);

        $amount = (float) $price->amount;
        $unitAmount = $amount >= 100 ? (int) round($amount) : (int) round($amount * 100);

        $stripePrice = $this->getStripeClient()->prices->create([
            'product' => $productId,
            'unit_amount' => max($unitAmount, 1),
            'currency' => strtolower((string) config('services.stripe.currency', 'USD')),
            'recurring' => [
                'interval' => $price->interval === 'annually' ? 'year' : 'month',
            ],
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'interval' => $price->interval,
            ],
        ]);

        $price->update([
            'stripe_price_id' => $stripePrice->id,
        ]);

        return $stripePrice->id;
    }

    private function getOrCreateStripeProduct(SubscriptionPlan $plan): string
    {
        if ($plan->stripe_product_id) {
            return $plan->stripe_product_id;
        }

        $stripeProduct = $this->getStripeClient()->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        $plan->update([
            'stripe_product_id' => $stripeProduct->id,
        ]);

        return $stripeProduct->id;
    }
}
