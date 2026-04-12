<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Policies\SubscriptionPolicy;
use App\Policies\PlanPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CustomerSubscription::class => SubscriptionPolicy::class,
        SubscriptionPlan::class => PlanPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, string $ability) {
            if (!config('filament-shield.super_admin.enabled')) {
                return null;
            }

            if ($ability === 'accessPanel') {
                return null;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }

    private function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
