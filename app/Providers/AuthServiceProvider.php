<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::before(function ($user) {
            if (! config('filament-shield.super_admin.enabled')) {
                return null;
            }

            return $user->hasRole(config('filament-shield.super_admin.name', 'admin')) ? true : null;
        });
    }
}
