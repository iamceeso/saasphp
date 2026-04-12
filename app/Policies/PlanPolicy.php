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
        return $plan->is_active || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isSuperAdmin();
    }

    public function managePlans(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
