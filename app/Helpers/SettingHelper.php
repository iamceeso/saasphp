<?php

namespace App\Helpers;

use App\Models\Setting;

class SettingHelper
{
    /**
     * Get a setting value by key, with optional default fallback.
     */
    public static function get(string $key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->type === 'boolean') {
            return $setting->value === 'true';
        }

        return $setting->value;
    }
}
