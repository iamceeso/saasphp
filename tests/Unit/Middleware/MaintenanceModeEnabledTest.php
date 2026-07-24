<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\MaintenanceModeEnabled;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Class MaintenanceModeEnabledTest.
 */
class MaintenanceModeEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(
            ['key' => 'features.maintenance_mode'],
            ['value' => 'true', 'type' => 'boolean', 'group' => 'features']
        );
    }

    public function test_allows_request_when_maintenance_mode_is_disabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.maintenance_mode'],
            ['value' => 'false', 'type' => 'boolean', 'group' => 'features']
        );

        $response = app(MaintenanceModeEnabled::class)->handle(
            Request::create('/dashboard', 'GET'),
            fn () => response('allowed')
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_blocks_request_when_maintenance_mode_is_enabled_and_user_lacks_bypass_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $response = app(MaintenanceModeEnabled::class)->handle(
            Request::create('/dashboard', 'GET'),
            fn () => response('allowed')
        );

        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_allows_request_when_user_has_bypass_maintenance_permission(): void
    {
        Permission::findOrCreate('by_pass_maintenance_role', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('by_pass_maintenance_role');

        $this->actingAs($user);

        $response = app(MaintenanceModeEnabled::class)->handle(
            Request::create('/dashboard', 'GET'),
            fn () => response('allowed')
        );

        $this->assertSame('allowed', $response->getContent());
    }
}
