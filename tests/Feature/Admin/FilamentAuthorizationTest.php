<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FilamentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_resource_exposes_expected_role_permission_crud_prefixes(): void
    {
        $this->assertContains('view_any', RoleResource::getPermissionPrefixes());
        $this->assertContains('create', RoleResource::getPermissionPrefixes());
        $this->assertContains('update_admin', RoleResource::getPermissionPrefixes());
        $this->assertContains('update_staff', RoleResource::getPermissionPrefixes());
        $this->assertContains('delete', RoleResource::getPermissionPrefixes());
        $this->assertContains('assign_core', RoleResource::getPermissionPrefixes());
    }

    public function test_user_resource_access_requires_view_any_user_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertFalse(UserResource::canAccess());

        Permission::findOrCreate('view_any_user', 'web');
        $user->givePermissionTo('view_any_user');

        $this->assertTrue(UserResource::canAccess());
    }

    public function test_role_resource_access_requires_view_any_role_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertFalse(RoleResource::canAccess());

        Permission::findOrCreate('view_any_role', 'web');
        $user->givePermissionTo('view_any_role');

        $this->assertTrue(RoleResource::canAccess());
    }

    public function test_role_policy_blocks_core_role_deletes_even_with_permission(): void
    {
        Permission::findOrCreate('delete_role', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('delete_role');

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $customRole = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);

        $this->assertFalse($user->can('delete', $adminRole));
        $this->assertFalse($user->can('delete', $userRole));
        $this->assertTrue($user->can('delete', $customRole));
    }

    public function test_impersonation_boundary_requires_permission_standard_user_and_verified_target(): void
    {
        Permission::findOrCreate('impersonate_role', 'web');

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        $this->assertFalse(UserResource::canImpersonateRecord($target));

        $admin->givePermissionTo('impersonate_role');
        $this->assertTrue(UserResource::canImpersonateRecord($target));

        $target->forceFill([
            'phone' => '+15555550001',
            'phone_verified_at' => null,
        ])->save();

        $this->assertFalse(UserResource::canImpersonateRecord($target->fresh()));
        $this->assertTrue(UserResource::canShowDisabledImpersonateRecord($target->fresh()));
    }
}
