<?php

namespace Tests\Unit\Services\Billing;

use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_subscription_does_not_require_stripe_configuration(): void
    {
        config()->set('services.stripe.secret', null);

        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        PlanPrice::factory()->for($plan, 'plan')->monthly()->create([
            'amount' => 0,
            'stripe_price_id' => null,
            'trial_days' => 0,
        ]);

        $result = app(SubscriptionService::class)->subscribe($user, $plan, 'monthly');

        $subscription = $result['subscription'];

        $this->assertInstanceOf(CustomerSubscription::class, $subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('free', $subscription->metadata['provider']);
        $this->assertStringStartsWith('sub_free_', $subscription->stripe_subscription_id);
        $this->assertFalse($result['requires_action']);
    }

    public function test_paid_plan_subscription_requires_stripe_configuration(): void
    {
        config()->set('services.stripe.secret', null);

        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        PlanPrice::factory()->for($plan, 'plan')->monthly()->create([
            'amount' => 2500,
            'stripe_price_id' => 'price_existing',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stripe secret key is not configured.');

        app(SubscriptionService::class)->subscribe($user, $plan, 'monthly', 'pm_card_visa');
    }

    public function test_existing_stripe_customer_id_is_reused_without_stripe_client(): void
    {
        config()->set('services.stripe.secret', null);

        $user = new User([
            'name' => 'Existing Customer',
            'email' => 'customer@example.com',
        ]);
        $user->stripe_id = 'cus_existing';

        $this->assertSame('cus_existing', app(SubscriptionService::class)->getOrCreateStripeCustomer($user));
    }

    public function test_local_subscription_cancel_does_not_require_stripe_configuration(): void
    {
        config()->set('services.stripe.secret', null);

        $subscription = CustomerSubscription::factory()->create([
            'metadata' => ['provider' => 'local'],
            'current_subscription_key' => 'user:1',
            'status' => 'active',
        ]);

        app(SubscriptionService::class)->cancel($subscription, immediately: true);

        $subscription->refresh();

        $this->assertSame('canceled', $subscription->status);
        $this->assertNull($subscription->current_subscription_key);
        $this->assertNotNull($subscription->ended_at);
    }
}
