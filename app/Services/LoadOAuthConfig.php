<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

                $row = Setting::where('key', $key)->first();
                if ($row && $row->value) {

                    if (empty($row->value)) {
                        Log::warning("Missing OAuth config: social.{$provider}.{$field}");
                    }

                    Config::set("services.{$provider}.{$field}", $row->value);
                }
            }

            // Redirect URI uses 'redirect' in services config
            $redirectKey = "social.{$provider}.redirect_uri";
            $row = Setting::where('key', $redirectKey)->first();
            if ($row && $row->value) {
                Config::set("services.{$provider}.redirect", $row->value);
            }
        }
    }
}
