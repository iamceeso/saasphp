<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\PlanPrice;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_route_exists()
    {
        $response = $this->get(route('pricing.show'));

        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_subscriptions_index_requires_auth()
    {
        $response = $this->get(route('subscriptions.index'));

        $this->assertEqual(302, $response->status());
    }

    public function test_authenticated_user_can_access_subscriptions_page()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('subscriptions.index'));

        $this->assertEqual(200, $response->status());
    }

    public function test_plan_with_prices_structure()
    {
        $plan = SubscriptionPlan::create([
            'slug' => 'pro',
            'name' => 'Professional',
            'description' => 'Professional plan',
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'amount' => 2999,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => 'annually',
            'amount' => 29990,
        ]);

        $monthlyPrice = $plan->getMonthlyPrice();
        $annuallyPrice = $plan->getAnnuallyPrice();

        $this->assertNotNull($monthlyPrice);
        $this->assertNotNull($annuallyPrice);
        $this->assertEqual(2999, $monthlyPrice->amount);
        $this->assertEqual(29990, $annuallyPrice->amount);
    }

    private function assertEqual($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }
}
