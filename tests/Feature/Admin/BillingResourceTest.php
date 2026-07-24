<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CustomerSubscriptionResource\Pages\EditCustomerSubscription;
use App\Filament\Resources\CustomerSubscriptionResource\Pages\ListCustomerSubscriptions;
use App\Filament\Resources\CustomerSubscriptionResource\Pages\ViewCustomerSubscription;
use App\Filament\Resources\SubscriptionPlanResource;
use App\Filament\Resources\SubscriptionPlanResource\Pages\CreateSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlanResource\Pages\EditSubscriptionPlan;
use App\Filament\Resources\SubscriptionPlanResource\Pages\ListSubscriptionPlans;
use App\Models\CustomerSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FilamentAuthorizationTest only checks canAccess()/impersonation gates.
 * These tests cover the actual page/CRUD behavior of the two billing
 * resources, which previously had zero coverage despite touching billing data
 * directly.
 */
class BillingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_subscription_plan_list_page_renders_existing_plans(): void
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Growth Plan']);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListSubscriptionPlans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$plan]);
    }

    public function test_subscription_plan_create_page_validates_required_fields(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateSubscriptionPlan::class)
            ->fillForm(['name' => '', 'slug' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'slug' => 'required']);

        $this->assertDatabaseCount('subscription_plans', 0);
    }

    public function test_subscription_plan_can_be_created_with_valid_data(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateSubscriptionPlan::class)
            ->fillForm([
                'name' => 'Scale',
                'slug' => 'scale',
                'description' => 'For growing teams',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subscription_plans', [
            'slug' => 'scale',
            'name' => 'Scale',
        ]);
    }

    public function test_subscription_plan_edit_page_loads_and_updates_the_record(): void
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditSubscriptionPlan::class, ['record' => $plan->getRouteKey()])
            ->assertSchemaStateSet(['name' => 'Old Name'])
            ->fillForm(['name' => 'New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Name', $plan->fresh()->name);
    }

    public function test_subscription_plan_edit_page_is_forbidden_for_non_super_admin(): void
    {
        // Staff has panel access (a privileged, non-"user" role) but PlanPolicy::update()
        // requires isSuperAdmin() specifically, so this isolates the plan-policy boundary
        // rather than the broader "can this role open the admin panel at all" boundary.
        // Livewire::test() silently swallows the abort_unless(...) HttpException that
        // Filament throws for a denied page, so this asserts over a real HTTP round trip
        // through the panel instead, where the exception becomes a real 403 response.
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $staff->assignRole('staff');

        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($staff)
            ->get(SubscriptionPlanResource::getUrl('edit', ['record' => $plan]))
            ->assertForbidden();
    }

    public function test_customer_subscription_list_page_renders_and_filters_by_status(): void
    {
        $active = CustomerSubscription::factory()->create(['status' => 'active']);
        $canceled = CustomerSubscription::factory()->create(['status' => 'canceled']);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListCustomerSubscriptions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$active, $canceled])
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$canceled]);
    }

    public function test_customer_subscription_view_page_renders(): void
    {
        $subscription = CustomerSubscription::factory()->create();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewCustomerSubscription::class, ['record' => $subscription->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_customer_subscription_edit_page_updates_status(): void
    {
        $subscription = CustomerSubscription::factory()->create(['status' => 'active']);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditCustomerSubscription::class, ['record' => $subscription->getRouteKey()])
            ->fillForm(['status' => 'past_due'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('past_due', $subscription->fresh()->status);
    }
}
