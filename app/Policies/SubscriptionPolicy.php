<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CustomerSubscription;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomerSubscription $subscription): bool
    {
        return $user->id === $subscription->user_id || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        // Keep policy logic aligned with app-level verification behavior/settings.
        return $user->hasVerifiedEmail();
    }

    public function update(User $user, CustomerSubscription $subscription): bool
    {
        if ($user->id !== $subscription->user_id && !$user->hasRole('admin')) {
            return false;
        }

        return !$subscription->isCanceled();
    }

    public function cancel(User $user, CustomerSubscription $subscription): bool
    {
        return $user->id === $subscription->user_id || $user->hasRole('admin');
    }

    public function resume(User $user, CustomerSubscription $subscription): bool
    {
        if ($user->id !== $subscription->user_id && !$user->hasRole('admin')) {
            return false;
        }

        return $subscription->onGracePeriod();
    }

    public function swapPlan(User $user, CustomerSubscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    public function changeBillingCycle(User $user, CustomerSubscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    public function viewInvoices(User $user, CustomerSubscription $subscription): bool
    {
        return $user->id === $subscription->user_id || $user->hasRole('admin');
    }
}
