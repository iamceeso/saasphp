<?php

namespace Tests\Feature\Settings;

use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::truncate();

        // Create user with required permission
        $this->user = User::factory()->create();

        // Allow all gates during test
        Gate::before(fn () => true);

        $this->actingAs($this->user);
    }

    #[Test]
    public function mount_populates_form_with_existing_settings()
    {
        Setting::create([
            'key' => 'site.name',
            'value' => 'Acme Corp',
            'type' => 'string',
            'group' => 'site',
        ]);
        Setting::create([
            'key' => 'site.theme',
            'value' => 'light',
            'type' => 'string',
            'group' => 'site',
        ]);

        Livewire::test(SiteSettings::class)
            ->assertSchemaStateSet([
                'data.site.name' => 'Acme Corp',
                'data.site.theme' => 'light',
            ]);
    }

    #[Test]
    public function validation_fails_if_required_fields_are_missing()
    {
        Livewire::test(SiteSettings::class)
            // we don’t set logo or the required selects
            ->call('submit')
            ->assertHasErrors([
                'data.site.timezone' => 'required',
                'data.site.date_format' => 'required',
                'data.site.language' => 'required',
            ]);
    }

    #[Test]
    public function file_upload_logo_is_optional_if_not_already_set()
    {
        // no logo seeded in DB
        Livewire::test(SiteSettings::class)
            ->call('submit')
            ->assertHasNoErrors(['data.site.logo']);
    }
}
