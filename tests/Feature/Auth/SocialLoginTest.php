<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(
            ['key' => 'features.enable_two_factor_auth'],
            ['value' => true, 'type' => 'boolean', 'group' => 'features']
        );
    }

    public function test_verified_google_user_can_sign_in_and_create_an_account(): void
    {
        $providerUser = new class {
            public string $email = 'social@example.com';
            public string $name = 'Social User';
            public array $user = ['verified_email' => true];

            public function getId(): string
            {
                return 'google-user-1';
            }
        };

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

    public function test_existing_local_account_is_not_auto_linked_by_social_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => now(),
        ]);

        $providerUser = new class {
            public string $email = 'existing@example.com';
            public string $name = 'Existing User';
            public array $user = ['verified_email' => true];

            public function getId(): string
            {
                return 'google-user-2';
            }
        };

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
}
