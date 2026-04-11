<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SubscriptionPlan;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SubscriptionPlan $plan): bool
    {
        return $plan->is_active || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $user->hasRole('admin');
    }

    public function managePlans(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
