<?php

namespace Tests\Unit\Policies;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

        $this->assertTrue($user->can('modify', \App\Models\Setting::class));
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

    public function test_view_no_role_permission_matches_generated_permission_name(): void
    {
        Permission::findOrCreate('view_no_role_role', 'web');

        $user = User::factory()->create(['email' => 'staff2@saasphp.com']);
        $user->assignRole('staff');
        $user->givePermissionTo('view_no_role_role');

        $this->assertTrue($user->can('viewNoRole', User::class));
    }
}
