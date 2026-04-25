<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SocialiteProviders for Microsoft and Yahoo
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', Provider::class);
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('yahoo', \SocialiteProviders\Yahoo\Provider::class);
        });

        // Guard Setting access until table exists
        if (! Schema::hasTable('settings')) {
            return;
        }

        // Flags
        Inertia::share([
            'twoFactorEnabled' => fn () => Setting::getBooleanValue('features.enable_two_factor_auth', false),
            'maintenanceModeEnabled' => fn () => Setting::getBooleanValue('features.maintenance_mode', false),
            'isImpersonating' => fn () => Session::has('impersonator_id'),
            'impersonator' => fn () => Session::has('impersonator_id')
                ? User::find(Session::get('impersonator_id'))?->only(['id', 'name', 'email'])
                : null,

            'social_ids' => fn () => [
                'google_id' => Setting::getValue('social.google.client_id', ''),
                'microsoft_id' => Setting::getValue('social.microsoft.client_id', ''),
                'yahoo_id' => Setting::getValue('social.yahoo.client_id', ''),
                'github_id' => Setting::getValue('social.github.client_id', ''),
                'twitter_id' => Setting::getValue('social.twitter.client_id', ''),
            ],
        ]);
    }
}
