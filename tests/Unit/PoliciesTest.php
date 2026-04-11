<?php

namespace Tests\Unit;

use App\Models\SubscriptionPlan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_plan_creates_successfully()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'description' => 'Pro plan',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('subscription_plans', [
            'slug' => 'pro',
            'name' => 'Professional',
        ]);
    }

    public function test_plan_price_belongs_to_plan()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'amount' => 2999,
        ]);

        $this->assertEquals($plan->id, $price->plan_id);
    }

    public function test_customer_subscription_tracks_status()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        $subscription = CustomerSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_customer_id' => 'cus_test_123',
            'status' => 'active',
            'interval' => 'monthly',
            'amount' => 2999,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isCanceled());
    }

    public function test_subscription_plan_has_active_scope()
    {
        SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        SubscriptionPlan::create([
            'slug' => 'free',
            'name' => 'Free',
            'is_active' => false,
        ]);

        $activePlans = SubscriptionPlan::active()->get();

        $this->assertCount(1, $activePlans);
    }

    public function test_plan_price_formatting()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        $price = PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'amount' => 2999,
        ]);

        $this->assertEquals('$29.99', $price->getFormattedAmount());
    }

    public function test_user_has_subscriptions_relationship()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 2; $i++) {
            CustomerSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => "sub_test_{$i}",
                'stripe_customer_id' => 'cus_test_123',
                'status' => 'active',
                'interval' => 'monthly',
                'amount' => 2999,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        }

        $this->assertCount(2, $user->subscriptions);
    }

    public function test_plan_features_can_be_attached()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        $plan->features()->create([
            'feature_key' => 'users',
            'feature_name' => 'Team Members',
            'value' => '5',
        ]);

        $this->assertTrue($plan->hasFeature('users'));
        $feature = $plan->getFeature('users');
        $this->assertEquals('5', $feature->getValue());
    }

    public function test_unlimited_feature_detection()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'is_active' => true,
        ]);

        $plan->features()->create([
            'feature_key' => 'storage',
            'feature_name' => 'Storage',
            'value' => 'unlimited',
        ]);

        $feature = $plan->getFeature('storage');
        $this->assertTrue($feature->isUnlimited());
    }
}
