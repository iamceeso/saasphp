<?php

namespace App\Providers;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Policies\PlanPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(CustomerSubscription::class, SubscriptionPolicy::class);
        Gate::policy(SubscriptionPlan::class, PlanPolicy::class);

        Gate::before(function ($user, string $ability) {
            if (! config('filament-shield.super_admin.enabled')) {
                return null;
            }

            if ($ability === 'accessPanel') {
                return null;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
