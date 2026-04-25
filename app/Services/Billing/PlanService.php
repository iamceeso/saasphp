<?php

namespace App\Services\Billing;

use App\Models\SubscriptionPlan;
use App\Models\PlanPrice;
use Stripe\StripeClient;

class PlanService
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

    public function createPlan(array $data): SubscriptionPlan
    {
        $stripeProductId = null;

        if ($this->requiresStripeProduct($data['prices'] ?? [])) {
            $product = $this->getStripeClient()->products->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => [
                    'slug' => $data['slug'],
                ],
            ]);

            $stripeProductId = $product->id;
        }

        $plan = SubscriptionPlan::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'stripe_product_id' => $stripeProductId,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'is_most_popular' => $data['is_most_popular'] ?? false,
            'cta_type' => $data['cta_type'] ?? 'subscribe',
            'contact_url' => $data['contact_url'] ?? null,
            'contact_button_text' => $data['contact_button_text'] ?? null,
        ]);

        if (isset($data['prices'])) {
            $this->attachPrices($plan, $data['prices']);
        }

        if (isset($data['features'])) {
            $this->attachFeatures($plan, $data['features']);
        }

        return $plan;
    }

    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        if ($plan->stripe_product_id) {
            $this->getStripeClient()->products->update($plan->stripe_product_id, [
                'name' => $data['name'] ?? $plan->name,
                'description' => $data['description'] ?? $plan->description,
            ]);
        }

        $plan->update($data);

        return $plan->refresh();
    }

    public function deletePlan(SubscriptionPlan $plan): void
    {
        if ($plan->stripe_product_id) {
            $this->getStripeClient()->products->update($plan->stripe_product_id, [
                'active' => false,
            ]);
        }

        $plan->update(['is_active' => false]);
    }

    public function attachPrices(SubscriptionPlan $plan, array $prices): void
    {
        foreach ($prices as $priceData) {
            $this->createPrice($plan, $priceData);
        }
    }

    public function createPrice(SubscriptionPlan $plan, array $data): PlanPrice
    {
        $unitAmount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_INT);

        if ($unitAmount === false || $unitAmount < 0) {
            throw new \InvalidArgumentException(
                'Plan price amount must be an integer in minor currency units and zero or greater.'
            );
        }

        $stripePriceId = null;

        if ($unitAmount > 0) {
            if (!$plan->stripe_product_id) {
                $plan->update([
                    'stripe_product_id' => $this->createStripeProductForPlan($plan),
                ]);
            }

            $stripePrice = $this->getStripeClient()->prices->create([
                'product' => $plan->stripe_product_id,
                'unit_amount' => $unitAmount,
                'currency' => strtolower((string) config('services.stripe.currency', 'USD')),
                'recurring' => [
                    'interval' => $data['interval'] === 'annually' ? 'year' : 'month',
                    'interval_count' => 1,
                ],
                'metadata' => [
                    'interval' => $data['interval'],
                    'trial_days' => (string) ($data['trial_days'] ?? 0),
                ],
            ]);

            $stripePriceId = $stripePrice->id;
        }

        return PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => $data['interval'],
            'amount' => $unitAmount,
            'trial_days' => $data['trial_days'] ?? 0,
            'stripe_price_id' => $stripePriceId,
            'is_active' => true,
        ]);
    }

    private function requiresStripeProduct(array $prices): bool
    {
        foreach ($prices as $price) {
            if ((int) ($price['amount'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function createStripeProductForPlan(SubscriptionPlan $plan): string
    {
        $product = $this->getStripeClient()->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'slug' => $plan->slug,
            ],
        ]);

        return $product->id;
    }

    public function attachFeatures(SubscriptionPlan $plan, array $features): void
    {
        foreach ($features as $featureKey => $featureData) {
            $plan->features()->create([
                'feature_key' => $featureKey,
                'feature_name' => $featureData['name'] ?? $featureKey,
                'description' => $featureData['description'] ?? null,
                'value' => $featureData['value'] ?? null,
            ]);
        }
    }

    public function getAllActivePlans()
    {
        return SubscriptionPlan::active()
            ->ordered()
            ->with(['prices' => fn ($q) => $q->active(), 'features'])
            ->get();
    }

    public function getPlanBySlug(string $slug): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)
            ->where('is_active', true)
            ->with(['prices' => fn ($q) => $q->active(), 'features'])
            ->first();
    }
}
