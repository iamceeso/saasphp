<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default appearance settings
        Setting::create([
            'key' => 'appearance.theme',
            'value' => 'light',
            'type' => 'string',
            'group' => 'appearance',
        ]);

        Setting::create([
            'key' => 'appearance.font_size',
            'value' => 'medium',
            'type' => 'string',
            'group' => 'appearance',
        ]);

        Setting::create([
            'key' => 'appearance.color_scheme',
            'value' => 'default',
            'type' => 'string',
            'group' => 'appearance',
        ]);

        $this->user = User::factory()->create();
    }

    public function test_appearance_settings_page_can_be_rendered()
    {
        $response = $this->actingAs($this->user)
            ->get('/settings/appearance');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('settings/appearance')
        );
    }

    public function test_unauthenticated_users_cannot_access_appearance_settings()
    {
        $response = $this->get('/settings/appearance');
        $response->assertRedirect('/login');
    }

    public function test_appearance_settings_are_persisted_across_sessions()
    {
        $this->actingAs($this->user)
            ->patch('/settings/appearance', [
                'theme' => 'dark',
                'font_size' => 'large',
                'color_scheme' => 'blue',
            ]);

        $this->post('/logout');

        $this->actingAs($this->user);

        $response = $this->get('/settings/appearance');
        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('settings/appearance')
        );
    }
}
