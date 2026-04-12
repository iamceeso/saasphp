<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Setting;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {

        parent::setUp();
        Setting::truncate();

        // Create necessary settings
        Setting::create([
            'key' => 'site.url',
            'value' => 'saasphp.com',
            'type' => 'string',
            'group' => 'site'
        ]);

        Setting::create([
            'key' => 'features.enable_email_verification',
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'features'
        ]);

        Setting::create([
            'key' => 'features.enable_phone_verification',
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'features'
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    }

    public function test_user_gets_default_role_on_creation()
    {
        $user = User::factory()->create();

        $this->assertTrue($user->hasRole('user'));
    }

    public function test_user_can_access_panel_when_conditions_are_met()
    {
        $user = User::factory()->create([
            'email' => 'test@saasphp.com',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue($user->canAccessPanel(new \Filament\Panel()));
    }

    public function test_user_cannot_access_panel_without_verified_email()
    {
        $user = User::factory()->create([
            'email' => 'test@saasphp.com',
            'email_verified_at' => null,
        ]);

        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse($user->canAccessPanel(new \Filament\Panel()));
    }

    public function test_verified_privileged_user_can_access_panel_regardless_of_site_domain()
    {
        $user = User::factory()->create([
            'email' => 'test@wrongdomain.com',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('staff');
        $this->actingAs($user);

        $this->assertTrue($user->canAccessPanel(new \Filament\Panel()));
    }

    public function test_user_role_does_not_block_panel_access_when_user_also_has_privileged_role()
    {
        $user = User::factory()->create([
            'email' => 'hybrid@example.com',
            'email_verified_at' => now(),
        ]);

        $user->assignRole('staff');
        $this->actingAs($user);

        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue($user->hasRole('staff'));
        $this->assertTrue($user->canAccessPanel(new \Filament\Panel()));
    }

    public function test_email_verification_status()
    {
        $user = User::factory()->create([
            'email_verified_at' => null
        ]);

        $this->assertFalse($user->hasVerifiedEmail());

        $user->markEmailAsVerified();

        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_phone_verification_status()
    {
        $user = User::factory()->create([
            'phone_verified_at' => null
        ]);

        $this->assertFalse($user->hasVerifiedPhone());

        $user->markPhoneAsVerified();

        $this->assertTrue($user->hasVerifiedPhone());
    }

    public function test_email_verification_rate_limiting()
    {
        $user = User::factory()->create();

        // First attempt should succeed
        $this->assertTrue($user->sendVerificationEmailWithRateLimit());

        // Second attempt should succeed
        $this->assertTrue($user->sendVerificationEmailWithRateLimit());

        // Third attempt should succeed
        $this->assertTrue($user->sendVerificationEmailWithRateLimit());

        // Fourth attempt should fail due to rate limiting
        $this->assertFalse($user->sendVerificationEmailWithRateLimit());
    }

    public function test_phone_verification_rate_limiting()
    {
        $user = User::factory()->create();

        // First attempt should succeed
        $this->assertTrue($user->sendPhoneVerificationCodeWithRateLimit());

        // Second attempt should succeed
        $this->assertTrue($user->sendPhoneVerificationCodeWithRateLimit());

        // Third attempt should succeed
        $this->assertTrue($user->sendPhoneVerificationCodeWithRateLimit());

        // Fourth attempt should fail due to rate limiting
        $this->assertFalse($user->sendPhoneVerificationCodeWithRateLimit());
    }

    public function test_email_verification_disabled_setting()
    {
        // Update setting to disable email verification
        Setting::where('key', 'features.enable_email_verification')
            ->update(['value' => encrypt('false')]);

        $user = User::factory()->create([
            'email_verified_at' => null
        ]);

        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_phone_verification_disabled_setting()
    {
        // Update setting to disable phone verification
        Setting::where('key', 'features.enable_phone_verification')
            ->update(['value' => encrypt('false')]);

        $user = User::factory()->create([
            'phone_verified_at' => null
        ]);

        $this->assertTrue($user->hasVerifiedPhone());
    }
}
