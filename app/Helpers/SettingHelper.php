<?php

namespace App\Helpers;

use App\Models\Setting;

class SettingHelper
{
    /**
     * Get a setting value by key, with optional default fallback.
     */
    public static function get(string $key, $default = null, bool $asBoolean = false)
    {
        if ($asBoolean) {
            return Setting::getBooleanValue($key, $default);
        }

        return Setting::getValue($key, $default);
    }
}
