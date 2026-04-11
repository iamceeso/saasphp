<?php

namespace App\Providers;

use Inertia\Inertia;

use App\Models\Setting;

use App\Services\LoadEmailConfig;
use App\Services\LoadOAuthConfig;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;


class SettingsServiceProvider extends ServiceProvider
{
    use LoadEmailConfig, LoadOAuthConfig;
    /**
     * Register any application services.
     */
    public function register(): void {}


    /**
     * Bootstrap settings and share them with the application.
     */
    public function boot(): void
    {


        // Defer logic until app is fully booted
        $this->app->booted(function () {
            // Only proceed if the 'settings' table exists
            if (!Schema::hasTable('settings')) {
                return;
            }

            // Set application locale from settings (default 'en')
            $language = Setting::getValue('site.language', 'en');
            if (!empty($language)) {
                App::setLocale($language);
            }

            // Share core site settings with Inertia views
            Inertia::share('site', [
                'name'         => Setting::getValue('site.name', 'SaaS PHP'),
                'url'          => Setting::getValue('site.url', 'https://saasphp.com'),
                'description'  => Setting::getValue('site.description', ''),
                'logo'         => Setting::getValue('site.logo', '/logos/logo.png'),
                'favicon'      => Setting::getValue('site.favicon', '/favicon.ico'),
                'theme'        => Setting::getValue('site.theme', 'default'),
                'timezone'     => Setting::getValue('site.timezone', 'UTC'),
                'currency'     => Setting::getValue('site.currency', 'NGN'),
                'language'     => $language,
                'date_format'  => Setting::getValue('site.date_format', 'Y-m-d'),
                'time_format'  => Setting::getValue('site.time_format', 'H:i:s'),
            ]);

            $this->loadOAuthConfig();
            $this->loadEmailConfig();
        });
    }
}
