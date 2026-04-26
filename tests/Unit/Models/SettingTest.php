<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_setting()
    {
        $setting = Setting::create([
            'key' => 'test.key',
            'value' => 'test value',
            'type' => 'string',
            'group' => 'test',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'test.key',
            'type' => 'string',
            'group' => 'test',
        ]);

        $this->assertEquals('test value', $setting->value);
    }

    public function test_can_get_setting_value()
    {
        Setting::create([
            'key' => 'test.key',
            'value' => 'test value',
            'type' => 'string',
            'group' => 'test',
        ]);

        $value = Setting::getValue('test.key');
        $this->assertEquals('test value', $value);
    }

    public function test_returns_default_value_when_setting_not_found()
    {
        $value = Setting::getValue('non.existent.key', 'default value');
        $this->assertEquals('default value', $value);
    }

    public function test_can_get_boolean_value()
    {
        Setting::create([
            'key' => 'test.boolean',
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'test',
        ]);

        $value = Setting::getBooleanValue('test.boolean');
        $this->assertTrue($value);
    }

    public function test_boolean_value_handles_various_true_values()
    {
        $trueValues = ['true', '1', 'yes', 'on'];

        foreach ($trueValues as $value) {
            Setting::create([
                'key' => "test.boolean.{$value}",
                'value' => $value,
                'type' => 'boolean',
                'group' => 'test',
            ]);

            $this->assertTrue(Setting::getBooleanValue("test.boolean.{$value}"));
        }
    }

    public function test_boolean_value_handles_various_false_values()
    {
        $falseValues = ['false', '0', 'no', 'off'];

        foreach ($falseValues as $value) {
            Setting::create([
                'key' => "test.boolean.{$value}",
                'value' => $value,
                'type' => 'boolean',
                'group' => 'test',
            ]);

            $this->assertFalse(Setting::getBooleanValue("test.boolean.{$value}"));
        }
    }

    public function test_returns_default_boolean_when_setting_not_found()
    {
        $this->assertTrue(Setting::getBooleanValue('non.existent.key', true));
        $this->assertFalse(Setting::getBooleanValue('non.existent.key', false));
    }

    public function test_updates_forget_cached_setting_values()
    {
        $setting = Setting::create([
            'key' => 'test.cached',
            'value' => 'first value',
            'type' => 'string',
            'group' => 'test',
        ]);

        $this->assertEquals('first value', Setting::getValue('test.cached'));

        $setting->update([
            'value' => 'updated value',
        ]);

        $this->assertEquals('updated value', Setting::getValue('test.cached'));
    }

    public function test_renaming_setting_forgets_old_and_new_cache_keys()
    {
        $setting = Setting::create([
            'key' => 'test.old',
            'value' => 'before rename',
            'type' => 'string',
            'group' => 'test',
        ]);

        $this->assertEquals('before rename', Setting::getValue('test.old'));

        $setting->update([
            'key' => 'test.new',
            'value' => 'after rename',
        ]);

        $this->assertNull(Setting::getValue('test.old'));
        $this->assertEquals('after rename', Setting::getValue('test.new'));
    }

    public function test_value_is_encrypted_in_database()
    {
        $setting = Setting::create([
            'key' => 'test.encrypted',
            'value' => 'sensitive data',
            'type' => 'string',
            'group' => 'test',
        ]);

        // The value in the database should be encrypted
        $this->assertNotEquals('sensitive data', $setting->getRawOriginal('value'));

        // But we should still be able to access it normally
        $this->assertEquals('sensitive data', $setting->value);
    }
}
