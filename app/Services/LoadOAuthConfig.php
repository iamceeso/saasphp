<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Trait LoadOAuthConfig.
 */
trait LoadOAuthConfig
{
    /**
     * Dynamically load OAuth provider credentials from the database.
     */
    public function loadOAuthConfig(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $providers = ['google', 'github', 'twitter', 'yahoo', 'microsoft'];

        foreach ($providers as $provider) {
            foreach (['client_id', 'client_secret'] as $field) {
                $key = "social.{$provider}.{$field}";
                $value = Setting::getValue($key);

                if (filled($value)) {
                    Config::set("services.{$provider}.{$field}", $value);
                }
            }

            // Redirect URI uses 'redirect' in services config
            $redirect = Setting::getValue("social.{$provider}.redirect_uri");
            if (filled($redirect)) {
                Config::set("services.{$provider}.redirect", $redirect);
            }
        }
    }
}
