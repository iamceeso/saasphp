<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a verified user
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_redirects_settings_to_profile()
    {
        $response = $this->actingAs($this->user)->get('/settings');
        $response->assertRedirect('/settings/profile');
    }

    public function test_can_view_profile_edit_page()
    {
        $response = $this->actingAs($this->user)->get('/settings/profile');
        $response->assertOk();
    }

    public function test_can_view_password_edit_page()
    {
        $response = $this->actingAs($this->user)->get('/settings/password');
        
        $response->assertOk();
    }

    public function test_can_view_appearance_page()
    {
        $response = $this->actingAs($this->user)->get('/settings/appearance');

        $response->assertInertia(fn (Assert $page) =>
            $page->component('settings/appearance')
        );
    }

    public function test_can_view_two_factor_edit_page()
    {
        $response = $this->actingAs($this->user)->get('/settings/security');
        $response->assertOk();
    }

    public function test_unauthenticated_users_cannot_access_settings()
    {
        $response = $this->get('/settings/profile');
        $response->assertRedirect('/login');
    }
}
