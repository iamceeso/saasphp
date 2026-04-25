<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_prompt_is_only_sent_once_per_session(): void
    {
        Setting::updateOrCreate(
            ['key' => 'features.enable_email_verification'],
            ['value' => true, 'type' => 'boolean', 'group' => 'features']
        );

        $user = User::factory()->unverified()->create([
            'phone' => null,
        ]);

        $this->actingAs($user);

        $this->get('/dashboard')->assertOk();
        $this->assertSame(1, cache()->get("verify-email:{$user->id}:attempts"));

        $this->get('/dashboard')->assertOk();
        $this->assertSame(1, cache()->get("verify-email:{$user->id}:attempts"));
    }
}
