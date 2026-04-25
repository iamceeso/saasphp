<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\PhoneCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp(); // Initialize Laravel framework and database

        Setting::truncate(); // Now safe to truncate after DB is ready

        // Enable phone verification feature
        Setting::firstOrCreate(
            ['key' => 'features.enable_phone_verification'],
            [
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'features',
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'features.sms_sending'],
            [
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'features',
            ]
        );

        $this->user = User::factory()->create([
            'phone' => '1234567890',
            'phone_verified_at' => null
        ]);
    }

    public function test_user_is_redirected_to_phone_verification_middleware()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertInertia(
            fn(Assert $page) =>
            $page->component('auth/verify-phone')
        );

        $response->assertStatus(200);
    }

    public function test_verification_code_can_be_sent()
    {
        $response = $this->actingAs($this->user)
            ->post('/phone/send');

        $response->assertSessionHas('status', 'verification-link-sent');
        $this->assertDatabaseHas('phone_codes', [
            'user_id' => $this->user->id
        ]);
    }

    public function test_verification_code_cannot_be_sent_without_phone()
    {
        $user = User::factory()->create([
            'phone' => null,
            'phone_verified_at' => null
        ]);

        $response = $this->actingAs($user)
            ->post('/phone/send');

        $response->assertSessionHasErrors('code');
    }

    public function test_phone_can_be_verified_with_valid_code()
    {
        // Create a verification code
        $code = '123456';
        PhoneCode::create([
            'user_id' => $this->user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)
        ]);

        $response = $this->actingAs($this->user)
            ->post('/phone/verify', [
                'code' => $code
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertNotNull($this->user->fresh()->phone_verified_at);
    }

    public function test_phone_cannot_be_verified_with_invalid_code()
    {
        $response = $this->actingAs($this->user)
            ->post('/phone/verify', [
                'code' => 'invalid-code'
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_phone_cannot_be_verified_with_expired_code()
    {
        // Create an expired verification code
        $code = '123456';
        PhoneCode::create([
            'user_id' => $this->user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->subMinutes(1)
        ]);

        $response = $this->actingAs($this->user)
            ->post('/phone/verify', [
                'code' => $code
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_verification_code_cannot_be_sent_too_frequently()
    {
        $this->actingAs($this->user);

        // Simulate hitting the rate limit
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('verify-phone:' . $this->user->id, 900); // 15 minutes
        }

        $response = $this->post('/phone/send');

        $response->assertSessionHasErrors('code');
    }


    public function test_phone_verification_is_not_required_when_disabled()
    {
        // Disable phone verification
        Setting::updateOrCreate(
            ['key' => 'features.enable_phone_verification'],
            ['value' => false, 'type' => 'boolean']
        );

        $response = $this->actingAs($this->user)
            ->get('/dashboard'); // or any route that uses EnsureUserIsVerified

        $response->assertStatus(200);
    }


    public function test_phone_verification_is_skipped_when_already_verified()
    {
        $user = User::factory()->create([
            'phone' => '1234567890',
            'phone_verified_at' => now()
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard');

        $response->assertInertia(
            fn(Assert $page) =>
            $page->component('dashboard')
        );

        $response->assertStatus(200);
    }


    public function test_used_verification_codes_cannot_be_reused()
    {
        $code = '123456';

        PhoneCode::create([
            'user_id' => $this->user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'used_at' => now() // simulate already used
        ]);

        $response = $this->actingAs($this->user)
            ->post('/phone/verify', [
                'code' => $code
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($this->user->fresh()->phone_verified_at);
    }
}
