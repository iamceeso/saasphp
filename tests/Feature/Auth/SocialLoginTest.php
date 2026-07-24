<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Class SocialLoginTest.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_google_user_can_sign_in_and_create_an_account(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => false, 'type' => 'boolean', 'group' => 'features']
        );

        $providerUser = $this->fakeProviderUser(
            id: 'google-user-1',
            email: 'social@example.com',
            name: 'Social User',
            raw: ['verified_email' => true],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'oauth_provider' => 'google',
            'oauth_provider_id' => 'google-user-1',
        ]);
    }

    public function test_social_login_redirect_routes_do_not_require_two_factor_authentication_to_be_enabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => false, 'type' => 'boolean', 'group' => 'features']
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.example.test/oauth'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $this->get('/login/google')->assertRedirect('https://accounts.example.test/oauth');
    }

    public function test_existing_local_account_is_not_auto_linked_by_social_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => now(),
        ]);

        $providerUser = $this->fakeProviderUser(
            id: 'google-user-2',
            email: 'existing@example.com',
            name: 'Existing User',
            raw: ['verified_email' => true],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'oauth_provider' => 'google',
            'oauth_provider_id' => 'google-user-2',
        ]);
    }

    public function test_github_user_with_verified_primary_email_can_sign_in(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'github-user-1',
            email: 'maintainer@example.com',
            name: 'Repo Maintainer',
            raw: ['login' => 'maintainer'],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('github')->once()->andReturn($driver);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'maintainer@example.com',
            'oauth_provider' => 'github',
            'oauth_provider_id' => 'github-user-1',
        ]);
    }

    public function test_twitter_user_with_email_can_sign_in(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'twitter-user-1',
            email: 'twitter@example.com',
            name: 'Twitter User',
            raw: ['screen_name' => 'twitteruser'],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('twitter')->once()->andReturn($driver);

        $response = $this->get('/auth/twitter/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'twitter@example.com',
            'oauth_provider' => 'twitter',
            'oauth_provider_id' => 'twitter-user-1',
        ]);
    }

    public function test_microsoft_email_must_have_an_explicit_verified_flag(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'microsoft-user-1',
            email: 'microsoft@example.com',
            name: 'Microsoft User',
            raw: [
                'mail' => 'microsoft@example.com',
                'userPrincipalName' => 'microsoft@example.com',
            ],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('microsoft')->once()->andReturn($driver);

        $response = $this->get('/auth/microsoft/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'microsoft@example.com',
        ]);
    }

    public function test_microsoft_user_with_verified_flag_can_sign_in(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'microsoft-user-2',
            email: 'verified-microsoft@example.com',
            name: 'Verified Microsoft User',
            raw: ['email_verified' => true],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('microsoft')->once()->andReturn($driver);

        $response = $this->get('/auth/microsoft/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'verified-microsoft@example.com',
            'oauth_provider' => 'microsoft',
            'oauth_provider_id' => 'microsoft-user-2',
        ]);
    }

    public function test_yahoo_email_without_a_verified_flag_is_rejected(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'yahoo-user-1',
            email: 'yahoo@example.com',
            name: 'Yahoo User',
            raw: [],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('yahoo')->once()->andReturn($driver);

        $response = $this->get('/auth/yahoo/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'yahoo@example.com',
        ]);
    }

    public function test_yahoo_user_with_verified_flag_can_sign_in(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'yahoo-user-2',
            email: 'verified-yahoo@example.com',
            name: 'Verified Yahoo User',
            raw: ['email_verified' => true],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('yahoo')->once()->andReturn($driver);

        $response = $this->get('/auth/yahoo/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'verified-yahoo@example.com',
            'oauth_provider' => 'yahoo',
            'oauth_provider_id' => 'yahoo-user-2',
        ]);
    }

    /**
     * Some providers serialize booleans as strings (e.g. "true"/"1") rather than
     * native booleans in their raw user payload. providerEmailIsVerified() runs
     * every flag through filter_var(..., FILTER_VALIDATE_BOOLEAN), so these
     * truthy string forms must still be accepted as verified.
     */
    public function test_truthy_string_verified_flags_are_accepted_across_providers(): void
    {
        $cases = [
            ['provider' => 'google', 'raw' => ['verified_email' => 'true']],
            ['provider' => 'microsoft', 'raw' => ['email_verified' => '1']],
            ['provider' => 'yahoo', 'raw' => ['email_verified' => 'yes']],
        ];

        foreach ($cases as $i => $case) {
            $email = "string-verified-{$case['provider']}@example.com";

            $providerUser = $this->fakeProviderUser(
                id: "{$case['provider']}-string-user-{$i}",
                email: $email,
                name: 'String Verified User',
                raw: $case['raw'],
            );

            $driver = Mockery::mock();
            $driver->shouldReceive('user')->once()->andReturn($providerUser);
            Socialite::shouldReceive('driver')->with($case['provider'])->once()->andReturn($driver);

            $response = $this->get("/auth/{$case['provider']}/callback");

            $response->assertRedirect('/dashboard');
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'oauth_provider' => $case['provider'],
            ]);

            $this->post('/logout');
        }
    }

    /**
     * A falsy-but-non-empty string ("0"/"false") must not be misread as
     * verified by filter_var's loose coercion.
     */
    public function test_falsy_string_verified_flags_are_rejected(): void
    {
        $providerUser = $this->fakeProviderUser(
            id: 'google-falsy-string-user',
            email: 'falsy-string@example.com',
            name: 'Falsy String User',
            raw: ['verified_email' => 'false'],
        );

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($providerUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'falsy-string@example.com',
        ]);
    }

    private function fakeProviderUser(string $id, ?string $email, ?string $name, array $raw = [], ?string $nickname = null): AbstractUser
    {
        return new class($id, $email, $name, $raw, $nickname) extends AbstractUser
        {
            public function __construct(
                private string $providerId,
                ?string $email,
                ?string $name,
                array $raw,
                ?string $nickname
            ) {
                $this->email = $email;
                $this->name = $name;
                $this->nickname = $nickname;
                $this->user = $raw;
            }

            public function getId()
            {
                return $this->providerId;
            }
        };
    }
}
