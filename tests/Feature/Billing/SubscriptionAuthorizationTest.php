<?php

namespace Tests\Feature\Billing;

use App\Models\CustomerSubscription;
use App\Models\PlanPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests ownership checks across subscription action endpoints.
 */
class SubscriptionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function localSubscriptionFor(User $owner, array $overrides = []): CustomerSubscription
    {
        return CustomerSubscription::factory()->create(array_merge([
            'user_id' => $owner->id,
            'status' => 'active',
            'ended_at' => null,
            'metadata' => ['provider' => 'local', 'currency' => 'USD'],
            'current_subscription_key' => 'user:'.$owner->id,
        ], $overrides));
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_show_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();
        $subscription = $this->localSubscriptionFor($owner);

        $this->actingAs($intruder)
            ->get(route('subscriptions.show', $subscription))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('subscriptions.show', $subscription))
            ->assertOk();
    }

    public function test_swap_plan_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();

        $currentPlan = SubscriptionPlan::factory()->create(['is_active' => true]);
        PlanPrice::factory()->create([
            'plan_id' => $currentPlan->id,
            'interval' => 'monthly',
            'amount' => 999,
            'is_active' => true,
        ]);

        $targetPlan = SubscriptionPlan::factory()->create(['is_active' => true]);
        PlanPrice::factory()->create([
            'plan_id' => $targetPlan->id,
            'interval' => 'monthly',
            'amount' => 0,
            'is_active' => true,
        ]);

        $subscription = $this->localSubscriptionFor($owner, [
            'plan_id' => $currentPlan->id,
            'interval' => 'monthly',
        ]);

        $this->actingAs($intruder)
            ->postJson(route('subscriptions.swap-plan', $subscription), [
                'plan_id' => $targetPlan->id,
                'interval' => 'monthly',
            ])
            ->assertForbidden();

        $this->assertSame($currentPlan->id, $subscription->fresh()->plan_id);

        $this->actingAs($owner)
            ->postJson(route('subscriptions.swap-plan', $subscription), [
                'plan_id' => $targetPlan->id,
                'interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($targetPlan->id, $subscription->fresh()->plan_id);
    }

    public function test_change_billing_cycle_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();

        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);
        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'interval' => 'monthly',
            'amount' => 999,
            'is_active' => true,
        ]);
        PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'interval' => 'annually',
            'amount' => 9990,
            'is_active' => true,
        ]);

        $subscription = $this->localSubscriptionFor($owner, [
            'plan_id' => $plan->id,
            'interval' => 'monthly',
        ]);

        $this->actingAs($intruder)
            ->postJson(route('subscriptions.change-cycle', $subscription), [
                'interval' => 'annually',
            ])
            ->assertForbidden();

        $this->assertSame('monthly', $subscription->fresh()->interval);

        $this->actingAs($owner)
            ->postJson(route('subscriptions.change-cycle', $subscription), [
                'interval' => 'annually',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('annually', $subscription->fresh()->interval);
    }

    public function test_cancel_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();
        $subscription = $this->localSubscriptionFor($owner);

        $this->actingAs($intruder)
            ->postJson(route('subscriptions.cancel', $subscription), ['immediately' => true])
            ->assertForbidden();

        $this->assertSame('active', $subscription->fresh()->status);

        $this->actingAs($owner)
            ->postJson(route('subscriptions.cancel', $subscription), ['immediately' => true])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('canceled', $subscription->fresh()->status);
    }

    public function test_resume_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();

        $subscription = $this->localSubscriptionFor($owner, [
            'status' => 'active',
            'canceled_at' => now()->subDay(),
            'current_period_end' => now()->addDays(5),
        ]);

        $this->actingAs($intruder)
            ->postJson(route('subscriptions.resume', $subscription))
            ->assertForbidden();

        $this->assertNotNull($subscription->fresh()->canceled_at);

        $this->actingAs($owner)
            ->postJson(route('subscriptions.resume', $subscription))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($subscription->fresh()->canceled_at);
    }

    public function test_invoices_is_scoped_to_the_owner(): void
    {
        $owner = $this->verifiedUser();
        $intruder = $this->verifiedUser();
        $subscription = $this->localSubscriptionFor($owner);

        $this->actingAs($intruder)
            ->get(route('subscriptions.invoices', $subscription))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('subscriptions.invoices', $subscription))
            ->assertOk();
    }

    public function test_super_admin_can_access_any_users_subscription(): void
    {
        $owner = $this->verifiedUser();
        $admin = $this->verifiedUser();
        $admin->assignRole('admin');

        $subscription = $this->localSubscriptionFor($owner);

        $this->actingAs($admin)
            ->get(route('subscriptions.show', $subscription))
            ->assertOk();
    }
}
