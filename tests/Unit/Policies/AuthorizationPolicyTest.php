<?php

namespace Tests\Unit\Policies;

use App\Helpers\RoleHelper;
use App\Models\CustomerSubscription;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::truncate();

        Setting::create([
            'key' => 'site.url',
            'value' => 'saasphp.com',
            'type' => 'string',
            'group' => 'site',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    }

    public function test_setting_policy_uses_modify_settings_role_permission(): void
    {
        Permission::findOrCreate('modify_settings_role', 'web');

        $user = User::factory()->create(['email' => 'staff@saasphp.com']);
        $user->assignRole('staff');
        $user->givePermissionTo('modify_settings_role');

        $this->assertTrue($user->can('modify', Setting::class));
    }

    public function test_non_admin_cannot_update_admin_user_even_with_update_permission(): void
    {
        Permission::findOrCreate('update_user', 'web');

        $editor = User::factory()->create(['email' => 'editor@saasphp.com']);
        $editor->assignRole('staff');
        $editor->givePermissionTo('update_user');

        $admin = User::factory()->create(['email' => 'admin@saasphp.com']);
        $admin->syncRoles(['admin']);

        $this->assertFalse($editor->can('update', $admin));
    }

    public function test_non_admin_cannot_update_privileged_staff_user_even_with_update_permission(): void
    {
        Permission::findOrCreate('update_user', 'web');

        $editor = User::factory()->create(['email' => 'editor2@example.com']);
        $editor->assignRole('staff');
        $editor->givePermissionTo('update_user');

        $manager = User::factory()->create(['email' => 'manager@example.com']);
        $manager->assignRole('staff');

        $this->assertFalse($editor->can('update', $manager));
    }

    public function test_view_no_role_permission_matches_generated_permission_name(): void
    {
        Permission::findOrCreate('view_no_role_role', 'web');

        $user = User::factory()->create(['email' => 'staff2@saasphp.com']);
        $user->assignRole('staff');
        $user->givePermissionTo('view_no_role_role');

        $this->assertTrue($user->can('viewNoRole', User::class));
    }

    public function test_verified_staff_user_can_access_panel_without_matching_site_domain(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.org',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('staff');

        $this->assertTrue($user->can('accessPanel', User::class));
    }

    public function test_subscription_create_policy_uses_verification_model_not_raw_column(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_email_verification'],
            ['value' => 'false', 'type' => 'boolean', 'group' => 'features']
        );

        $user = User::factory()->create([
            'email' => 'subscriber@saasphp.com',
            'email_verified_at' => null,
        ]);

        $this->assertTrue($user->can('create', CustomerSubscription::class));
    }

    public function test_non_core_role_manager_cannot_submit_role_management_permissions(): void
    {
        Permission::findOrCreate('create_role', 'web');
        Permission::findOrCreate('update_staff_role', 'web');

        $user = User::factory()->create(['email' => 'role-manager@example.com']);
        $user->assignRole('staff');
        $user->givePermissionTo(['create_role', 'update_staff_role']);

        $this->actingAs($user);
        $this->expectException(HttpException::class);

        RoleHelper::assertCanSubmitRolePermissions(collect(['assign_core_role']));
    }

    public function test_assign_core_role_permission_can_submit_role_management_permissions(): void
    {
        Permission::findOrCreate('assign_core_role', 'web');

        $user = User::factory()->create(['email' => 'core-manager@example.com']);
        $user->assignRole('staff');
        $user->givePermissionTo('assign_core_role');

        $this->actingAs($user);

        RoleHelper::assertCanSubmitRolePermissions(collect(['assign_core_role']));

        $this->assertTrue(true);
    }

    public function test_non_core_role_manager_cannot_assign_custom_role_with_role_management_permissions(): void
    {
        Permission::findOrCreate('update_staff_role', 'web');
        Permission::findOrCreate('assign_core_role', 'web');

        $manager = User::factory()->create(['email' => 'staff-manager@example.com']);
        $manager->assignRole('staff');
        $manager->givePermissionTo('update_staff_role');

        $elevatedRole = Role::create(['name' => 'ops-admin', 'guard_name' => 'web']);
        $elevatedRole->givePermissionTo('assign_core_role');

        $this->actingAs($manager);

        $this->assertTrue($manager->can('update', $elevatedRole));
        $this->assertFalse(RoleHelper::canManageAssignments($elevatedRole->fresh()));
    }

    public function test_assign_core_role_permission_can_assign_custom_role_with_role_management_permissions(): void
    {
        Permission::findOrCreate('update_staff_role', 'web');
        Permission::findOrCreate('assign_core_role', 'web');

        $manager = User::factory()->create(['email' => 'core-staff-manager@example.com']);
        $manager->assignRole('staff');
        $manager->givePermissionTo(['update_staff_role', 'assign_core_role']);

        $elevatedRole = Role::create(['name' => 'ops-admin', 'guard_name' => 'web']);
        $elevatedRole->givePermissionTo('assign_core_role');

        $this->actingAs($manager);

        $this->assertTrue(RoleHelper::canManageAssignments($elevatedRole->fresh()));
    }
}
