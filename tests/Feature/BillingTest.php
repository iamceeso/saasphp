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

    public function test_stripe_webhook_route_is_exempt_from_csrf_protection()
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test');

        $response = $this->post(route('webhooks.stripe'), []);

        $this->assertNotEquals(419, $response->status());
        $this->assertEqual(400, $response->status());
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

    public function test_authenticated_user_can_subscribe_to_free_plan_without_payment_method()
    {
        $user = User::create([
            'name' => 'Free User',
            'email' => 'free@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $plan = SubscriptionPlan::create([
            'slug' => 'free',
            'name' => 'Free',
            'description' => 'Free tier',
            'is_active' => true,
        ]);

        PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'amount' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('subscribe'), [
            'plan_id' => $plan->id,
            'interval' => 'monthly',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('customer_subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'amount' => 0,
        ]);

        $subscription = CustomerSubscription::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('free', data_get($subscription->metadata, 'provider'));
    }

    private function assertEqual($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }
}
