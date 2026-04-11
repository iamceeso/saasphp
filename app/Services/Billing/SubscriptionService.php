<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        try {
            return DB::transaction(
                fn () => $this->syncSubscriptionToDB($user, $plan, $subscription, $interval)
            );
        } catch (\Throwable $e) {
            try {
                $this->getStripeClient()->subscriptions->cancel($subscription->id, [
                    'invoice_now' => false,
                    'prorate' => false,
                ]);
            } catch (\Throwable $rollbackException) {
                Log::error('Failed to rollback Stripe subscription after local persistence error', [
                    'user_id' => $user->id,
                    'stripe_subscription_id' => $subscription->id,
                    'error' => $rollbackException->getMessage(),
                ]);
            }

            throw $e;
        }
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
            return DB::transaction(function () use ($subscription, $newPlan, $interval, $newPrice) {
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'interval' => $interval,
                    'amount' => $newPrice->amount,
                    'current_subscription_key' => $this->resolveCurrentSubscriptionKey(
                        $subscription->user_id,
                        $subscription->status,
                        $subscription->ended_at,
                    ),
                ]);

                return $subscription->refresh();
            });
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

        return DB::transaction(function () use ($subscription, $newPlan, $interval, $updatedStripeSubscription) {
            $subscription->update(array_merge(
                ['plan_id' => $newPlan->id],
                $this->mapStripeSubscription($updatedStripeSubscription, $newPlan, $interval)
            ));

            return $subscription->refresh();
        });
    }

    public function cancel(CustomerSubscription $subscription, bool $immediately = false): void
    {
        if ($immediately) {
            $stripeSubscription = $this->getStripeClient()->subscriptions->cancel($subscription->stripe_subscription_id, [
                'invoice_now' => false,
            ]);

            DB::transaction(function () use ($subscription, $stripeSubscription) {
                $subscription->update([
                    'current_subscription_key' => null,
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ended_at' => $stripeSubscription->ended_at
                        ? Carbon::createFromTimestamp((int) $stripeSubscription->ended_at)
                        : now(),
                ]);
            });

            return;
        }

        $this->getStripeClient()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'canceled_at' => now(),
                'current_subscription_key' => $this->currentSubscriptionKeyFor($subscription->user_id),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancel_at_period_end' => true,
                ]),
            ]);
        });
    }

    public function resume(CustomerSubscription $subscription): CustomerSubscription
    {
        $this->getStripeClient()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        return DB::transaction(function () use ($subscription) {
            $subscription->update([
                'current_subscription_key' => $this->currentSubscriptionKeyFor($subscription->user_id),
                'status' => 'active',
                'canceled_at' => null,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancel_at_period_end' => false,
                ]),
            ]);

            return $subscription->refresh();
        });
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

    public function normalizeCurrentSubscriptions(User $user): ?CustomerSubscription
    {
        $currentSubscriptions = $user->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->get();

        $current = $currentSubscriptions->first();

        if (!$current) {
            return null;
        }

        $duplicates = $currentSubscriptions->slice(1);

        foreach ($duplicates as $duplicate) {
            $provider = data_get($duplicate->metadata, 'provider');

            if ($provider === 'stripe' && !empty($duplicate->stripe_subscription_id)) {
                try {
                    $this->getStripeClient()->subscriptions->cancel($duplicate->stripe_subscription_id, [
                        'invoice_now' => false,
                        'prorate' => false,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to cancel duplicate Stripe subscription during normalization', [
                        'user_id' => $user->id,
                        'subscription_id' => $duplicate->id,
                        'stripe_subscription_id' => $duplicate->stripe_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $duplicate->update([
                'current_subscription_key' => null,
                'status' => 'canceled',
                'canceled_at' => now(),
                'ended_at' => now(),
                'metadata' => array_merge($duplicate->metadata ?? [], [
                    'normalized_duplicate' => true,
                ]),
            ]);
        }

        return $current->refresh();
    }

    public function currentSubscriptionKeyFor(int $userId): string
    {
        return "user:{$userId}";
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
            $existingSubscription->update(array_merge(
                [
                    'current_subscription_key' => $this->resolveCurrentSubscriptionKey(
                        $user->id,
                        $stripeSubscription->status,
                        data_get($stripeSubscription, 'ended_at'),
                    ),
                ],
                $this->mapStripeSubscription($stripeSubscription, $plan, $interval)
            ));
            return $existingSubscription;
        }

        return CustomerSubscription::updateOrCreate(
            [
                'stripe_subscription_id' => $stripeSubscription->id,
            ],
            array_merge(
                [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'current_subscription_key' => $this->resolveCurrentSubscriptionKey(
                        $user->id,
                        $stripeSubscription->status,
                        data_get($stripeSubscription, 'ended_at'),
                    ),
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
                'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
            ],
        ];
    }

    public function resolveCurrentSubscriptionKey(int $userId, string $status, mixed $endedAt = null): ?string
    {
        if (in_array($status, CustomerSubscription::CURRENT_SLOT_STATUSES, true) && blank($endedAt)) {
            return $this->currentSubscriptionKeyFor($userId);
        }

        return null;
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
