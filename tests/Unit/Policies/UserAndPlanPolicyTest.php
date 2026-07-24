<?php

namespace Tests\Unit\Policies;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests user and plan policy edge cases across role boundaries.
 */
class UserAndPlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }

    public function test_impersonate_requires_the_impersonate_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('impersonate', User::class));

        Permission::findOrCreate('impersonate_role', 'web');
        $user->givePermissionTo('impersonate_role');

        $this->assertTrue($user->can('impersonate', User::class));
    }

    public function test_delete_any_requires_the_delete_any_user_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('deleteAny', User::class));

        Permission::findOrCreate('delete_any_user', 'web');
        $user->givePermissionTo('delete_any_user');

        $this->assertTrue($user->can('deleteAny', User::class));
    }

    public function test_force_delete_any_requires_the_force_delete_any_user_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('forceDeleteAny', User::class));

        Permission::findOrCreate('force_delete_any_user', 'web');
        $user->givePermissionTo('force_delete_any_user');

        $this->assertTrue($user->can('forceDeleteAny', User::class));
    }

    public function test_force_delete_requires_permission_and_target_management_boundary(): void
    {
        Permission::findOrCreate('force_delete_user', 'web');

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('force_delete_user');

        $standardTarget = User::factory()->create();
        $this->assertTrue($staff->can('forceDelete', $standardTarget));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertFalse($staff->can('forceDelete', $admin));
    }

    public function test_restore_requires_permission_and_target_management_boundary(): void
    {
        Permission::findOrCreate('restore_user', 'web');

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('restore_user');

        $standardTarget = User::factory()->create();
        $this->assertTrue($staff->can('restore', $standardTarget));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertFalse($staff->can('restore', $admin));
    }

    public function test_restore_any_requires_the_restore_any_user_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('restoreAny', User::class));

        Permission::findOrCreate('restore_any_user', 'web');
        $user->givePermissionTo('restore_any_user');

        $this->assertTrue($user->can('restoreAny', User::class));
    }

    public function test_bypass_maintenance_role_checks_the_permission_directly(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('byPassMaintenanceRole', User::class));

        Permission::findOrCreate('by_pass_maintenance_role', 'web');
        $user->givePermissionTo('by_pass_maintenance_role');

        $this->assertTrue($user->can('byPassMaintenanceRole', User::class));
    }

    public function test_plan_view_is_public_for_active_plans_but_restricted_for_inactive_plans(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activePlan = SubscriptionPlan::factory()->create(['is_active' => true]);
        $inactivePlan = SubscriptionPlan::factory()->create(['is_active' => false]);

        $this->assertTrue($user->can('view', $activePlan));
        $this->assertFalse($user->can('view', $inactivePlan));
        $this->assertTrue($admin->can('view', $inactivePlan));
    }

    public function test_plan_create_update_delete_and_manage_are_restricted_to_super_admins(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = SubscriptionPlan::factory()->create();

        $this->assertFalse($user->can('create', SubscriptionPlan::class));
        $this->assertFalse($user->can('update', $plan));
        $this->assertFalse($user->can('delete', $plan));
        $this->assertFalse($user->can('managePlans', SubscriptionPlan::class));

        $this->assertTrue($admin->can('create', SubscriptionPlan::class));
        $this->assertTrue($admin->can('update', $plan));
        $this->assertTrue($admin->can('delete', $plan));
        $this->assertTrue($admin->can('managePlans', SubscriptionPlan::class));
    }
}
