<?php

namespace Tests\Unit\Actions\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Events\ImageUpdated;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Class UpdateSettingsTest.
 */
class UpdateSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_existing_logo_dispatches_image_updated_once(): void
    {
        Setting::create([
            'key' => 'site.logo',
            'value' => 'logos/old.png',
            'type' => 'string',
            'group' => 'site',
        ]);

        Event::fake([ImageUpdated::class]);

        app(UpdateSettings::class)->handle([
            'site' => [
                'logo' => 'logos/new.png',
            ],
        ]);

        Event::assertDispatchedTimes(ImageUpdated::class, 1);
        $this->assertSame('logos/new.png', Setting::getValue('site.logo'));
    }
}
