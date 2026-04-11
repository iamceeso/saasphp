<?php

namespace Tests\Unit\Models;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_remains_active_during_grace_period(): void
    {
        $subscription = new CustomerSubscription([
            'status' => 'active',
            'current_period_end' => Carbon::now()->addDays(7),
            'canceled_at' => Carbon::now(),
            'ended_at' => null,
        ]);

        $this->assertTrue($subscription->onGracePeriod());
        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isCanceled());
    }

    public function test_subscription_is_canceled_once_it_has_ended(): void
    {
        $subscription = new CustomerSubscription([
            'status' => 'canceled',
            'current_period_end' => Carbon::now()->subDay(),
            'canceled_at' => Carbon::now()->subDays(5),
            'ended_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->isActive());
        $this->assertTrue($subscription->isCanceled());
        $this->assertNull($subscription->getNextBillingDate());
    }

    public function test_database_allows_only_one_current_subscription_slot_per_user(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'slug' => 'guarded-plan',
            'name' => 'Guarded Plan',
            'description' => 'Plan used to verify current subscription uniqueness.',
            'is_active' => true,
        ]);

        CustomerSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'current_subscription_key' => "user:{$user->id}",
            'stripe_subscription_id' => 'sub_guarded_first',
            'stripe_customer_id' => 'cus_guarded',
            'status' => 'active',
            'interval' => 'monthly',
            'amount' => 999,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'ended_at' => null,
        ]);

        $this->expectException(QueryException::class);

        CustomerSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'current_subscription_key' => "user:{$user->id}",
            'stripe_subscription_id' => 'sub_guarded_second',
            'stripe_customer_id' => 'cus_guarded',
            'status' => 'trialing',
            'interval' => 'monthly',
            'amount' => 999,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'ended_at' => null,
        ]);
    }
}
