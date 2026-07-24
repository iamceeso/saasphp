<?php

namespace Tests\Unit\Actions\Billing;

use App\Actions\Billing\SubscribeToPlan;
use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests each SubscribeToPlan decision path directly.
 */
class SubscribeToPlanTest extends TestCase
{
    use RefreshDatabase;

    private function planWithPrice(int $amount, string $interval = 'monthly'): SubscriptionPlan
    {
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'interval' => $interval,
            'amount' => $amount,
            'is_active' => true,
        ]);

        return $plan;
    }

    public function test_it_subscribes_when_the_user_has_no_current_subscription(): void
    {
        $user = User::factory()->create();
        $plan = $this->planWithPrice(0);

        $result = app(SubscribeToPlan::class)->handle($user, $plan, 'monthly');

        $this->assertSame('active', $result['subscription']->status);
        $this->assertSame($plan->id, $result['subscription']->plan_id);
        $this->assertFalse($result['requires_action']);
    }

    public function test_it_reuses_the_existing_subscription_when_plan_and_interval_are_unchanged(): void
    {
        $user = User::factory()->create();
        $plan = $this->planWithPrice(0);

        $existing = CustomerSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'status' => 'active',
            'ended_at' => null,
            'current_subscription_key' => 'user:'.$user->id,
            'metadata' => ['provider' => 'local'],
        ]);

        $result = app(SubscribeToPlan::class)->handle($user, $plan, 'monthly');

        $this->assertSame($existing->id, $result['subscription']->id);
        $this->assertNull($result['payment_intent_client_secret']);
    }

    public function test_it_changes_billing_cycle_when_only_the_interval_differs(): void
    {
        $user = User::factory()->create();
        $plan = $this->planWithPrice(999, 'monthly');
        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'interval' => 'annually',
            'amount' => 9990,
            'is_active' => true,
        ]);

        $existing = CustomerSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'status' => 'active',
            'ended_at' => null,
            'current_subscription_key' => 'user:'.$user->id,
            'metadata' => ['provider' => 'local'],
        ]);

        $result = app(SubscribeToPlan::class)->handle($user, $plan, 'annually');

        $this->assertSame($existing->id, $result['subscription']->id);
        $this->assertSame('annually', $result['subscription']->interval);
    }

    public function test_it_swaps_to_a_different_free_plan_without_a_payment_method(): void
    {
        $user = User::factory()->create();
        $currentPlan = $this->planWithPrice(999);
        $freePlan = $this->planWithPrice(0);

        $existing = CustomerSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $currentPlan->id,
            'interval' => 'monthly',
            'status' => 'active',
            'ended_at' => null,
            'current_subscription_key' => 'user:'.$user->id,
            'metadata' => ['provider' => 'local'],
        ]);

        $result = app(SubscribeToPlan::class)->handle($user, $freePlan, 'monthly');

        $this->assertSame($existing->id, $result['subscription']->id);
        $this->assertSame($freePlan->id, $result['subscription']->plan_id);
        $this->assertSame('free', data_get($result['subscription']->metadata, 'provider'));
    }

    public function test_it_swaps_between_paid_plans_when_current_subscription_is_locally_managed(): void
    {
        $user = User::factory()->create();
        $currentPlan = $this->planWithPrice(999);
        $targetPlan = $this->planWithPrice(2999);

        $existing = CustomerSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $currentPlan->id,
            'interval' => 'monthly',
            'status' => 'active',
            'ended_at' => null,
            'current_subscription_key' => 'user:'.$user->id,
            'metadata' => ['provider' => 'local'],
        ]);

        $result = app(SubscribeToPlan::class)->handle($user, $targetPlan, 'monthly');

        $this->assertSame($existing->id, $result['subscription']->id);
        $this->assertSame($targetPlan->id, $result['subscription']->plan_id);
        $this->assertSame(2999, $result['subscription']->amount);
    }

    public function test_it_requires_a_payment_method_to_upgrade_from_a_free_plan(): void
    {
        $user = User::factory()->create();
        $freePlan = $this->planWithPrice(0);
        $paidPlan = $this->planWithPrice(2999);

        CustomerSubscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $freePlan->id,
            'interval' => 'monthly',
            'status' => 'active',
            'ended_at' => null,
            'current_subscription_key' => 'user:'.$user->id,
            'metadata' => ['provider' => 'free'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A payment method is required to upgrade from a free plan.');

        app(SubscribeToPlan::class)->handle($user, $paidPlan, 'monthly');
    }
}
