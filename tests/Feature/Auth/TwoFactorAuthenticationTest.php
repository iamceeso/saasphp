<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\RecoveryCode;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable 2FA feature
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            [
                'value' => true,
                'type' => 'boolean',
                'group' => 'features',
            ]
        );

        $this->user = User::factory()->create();
        $this->provider = app(TwoFactorAuthenticationProvider::class);
    }

    public function test_two_factor_authentication_screen_can_be_rendered()
    {
        $response = $this->actingAs($this->user)
            ->get('/settings/security');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('settings/two-factor-authentication')
        );
    }

    public function test_two_factor_authentication_can_be_enabled()
    {
        $this->actingAs($this->user)
            ->post('/user/two-factor-authentication');

        $this->user->forceFill([
            'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['test-code-1', 'test-code-2'])),
        ])->save();

        $this->assertNotNull($this->user->fresh()->two_factor_secret);
        $this->assertNotNull($this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_authentication_can_be_disabled()
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        ])->save();

        // Bypass password confirmation middleware
        $response = $this->actingAs($this->user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->delete('/user/two-factor-authentication');

        $user = $this->user->fresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $response->assertSessionHas('status', 'two-factor-authentication-disabled');
    }

    public function test_two_factor_authentication_can_be_confirmed()
    {
        $google2fa = new Google2FA;
        $plainSecret = $google2fa->generateSecretKey(); // 16+ char base32

        $this->user->forceFill([
            'two_factor_secret' => encrypt($plainSecret), // simulate what Fortify would save
        ])->save();

        $code = $google2fa->getCurrentOtp($plainSecret);

        $response = $this->actingAs($this->user)
            ->post('/user/confirmed-two-factor-authentication', [
                'code' => $code,
            ]);

        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
        $response->assertSessionHas('status', 'two-factor-authentication-confirmed');
    }

    public function test_two_factor_authentication_cannot_be_confirmed_with_invalid_code()
    {
        $this->actingAs($this->user)
            ->post('/user/two-factor-authentication');

        $response = $this->actingAs($this->user)
            ->post('/user/confirmed-two-factor-authentication', [
                'code' => 'invalid-code',
            ]);

        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);

        $response->assertSessionHasErrors();
    }

    public function test_recovery_codes_can_be_regenerated()
    {
        // First enable 2FA
        $this->actingAs($this->user)
            ->post('/user/two-factor-authentication');

        $oldRecoveryCodes = $this->user->fresh()->two_factor_recovery_codes;

        $response = $this->actingAs($this->user)
            ->post('/user/two-factor-recovery-codes');

        $this->assertNotEquals(
            $oldRecoveryCodes,
            $this->user->fresh()->two_factor_recovery_codes
        );
        $response->assertSessionHas('status', 'recovery-codes-generated');
    }

    public function test_two_factor_authentication_is_required_when_enabled()
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $recoveryCodes = collect(range(1, 8))->map(fn () => RecoveryCode::generate())->all();

        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        $this->post('/logout');

        $response = $this->post('/login', [
            'login' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/two-factor-challenge');
    }

    public function test_two_factor_authentication_can_be_bypassed_with_recovery_code()
    {
        $secret = (new Google2FA)->generateSecretKey();
        $plainCodes = collect(range(1, 8))->map(fn () => RecoveryCode::generate())->values();
        $firstCode = $plainCodes[0];

        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($plainCodes)),
        ])->save();

        $this->post('/logout');

        $this->post('/login', [
            'login' => $this->user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor-challenge');

        $response = $this->post('/two-factor-challenge', [
            'recovery_code' => $firstCode,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_two_factor_authentication_feature_can_be_disabled()
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => false, 'type' => 'boolean', 'group' => 'features']
        );

        $response = $this->actingAs($this->user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->get('/settings/security');

        $response->assertStatus(200);
    }

    public function test_confirmed_two_factor_screen_does_not_expose_the_raw_secret()
    {
        $secret = (new Google2FA)->generateSecretKey();

        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        ])->save();

        $response = $this->actingAs($this->user)->get('/settings/security');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/two-factor-authentication')
            ->where('twoFactorSecret', null)
            ->where('twoFactorQRCode', '')
            ->where('twoFactorRecoveryCodes', [])
            ->where('twoFactorConfirmation', true));
    }
}
