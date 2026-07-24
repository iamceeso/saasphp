<?php

namespace Tests\Unit\Services\Billing;

use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;
use Tests\TestCase;

/**
 * Tests Stripe-backed subscription flows through the Stripe SDK transport seam.
 */
class StripeBackedSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.secret', 'sk_test_fake');

        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_subscribing_to_a_paid_plan_creates_a_stripe_backed_local_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['stripe_product_id' => null]);
        $price = PlanPrice::factory()->for($plan, 'plan')->monthly()->create([
            'amount' => 2999,
            'stripe_price_id' => null,
        ]);

        $result = app(SubscriptionService::class)->subscribe($user, $plan, 'monthly', 'pm_card_visa');

        $subscription = $result['subscription'];

        $this->assertInstanceOf(CustomerSubscription::class, $subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('stripe', data_get($subscription->metadata, 'provider'));
        $this->assertStringStartsWith('sub_fake_', $subscription->stripe_subscription_id);
        $this->assertSame('pi_fake_1_secret_test', $result['payment_intent_client_secret']);
        $this->assertSame('succeeded', $result['payment_intent_status']);
        $this->assertFalse($result['requires_action']);

        // The Stripe product/price created along the way should be cached locally
        // so a second subscribe for the same plan/price doesn't recreate them.
        $this->assertNotNull($plan->fresh()->stripe_product_id);
        $this->assertNotNull($price->fresh()->stripe_price_id);

        // Regression guard: the newly-created Stripe customer id must actually
        // persist to the user record (not just be returned in-memory), otherwise
        // every future subscribe attempt for this user creates a duplicate
        // Stripe customer instead of reusing this one.
        $this->assertNotNull($user->fresh()->stripe_id);
        $this->assertStringStartsWith('cus_fake_', $user->fresh()->stripe_id);

        // A real customer, payment-method attach, and subscription create round trip happened.
        $methods = array_column($this->stripe->requests, 'method');
        $paths = array_map(fn ($r) => parse_url($r['url'], PHP_URL_PATH), $this->stripe->requests);

        $this->assertContains('/v1/customers', $paths);
        $this->assertContains('/v1/subscriptions', $paths);
        $this->assertTrue(collect($paths)->contains(fn ($p) => str_ends_with($p, '/attach')));
    }

    public function test_subscribing_reuses_an_existing_stripe_customer_id(): void
    {
        $user = User::factory()->create(['stripe_id' => 'cus_existing_reused']);
        $plan = SubscriptionPlan::factory()->create();
        PlanPrice::factory()->for($plan, 'plan')->monthly()->create([
            'amount' => 1999,
            'stripe_price_id' => 'price_already_cached',
        ]);

        app(SubscriptionService::class)->subscribe($user, $plan, 'monthly', 'pm_card_visa');

        $customerCreateCalls = collect($this->stripe->requests)
            ->filter(fn ($r) => parse_url($r['url'], PHP_URL_PATH) === '/v1/customers' && $r['method'] === 'post');

        $this->assertCount(0, $customerCreateCalls);
    }
}
