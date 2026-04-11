<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;

class SubscribeToPlan
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(
        User $user,
        SubscriptionPlan $plan,
        string $interval,
        ?string $paymentMethod = null
    ): CustomerSubscription {
        $currentSubscription = $this->subscriptionService->normalizeCurrentSubscriptions($user);

        if ($currentSubscription instanceof CustomerSubscription) {
            if ((int) $currentSubscription->plan_id === (int) $plan->id && $currentSubscription->interval === $interval) {
                return $currentSubscription->refresh();
            }

            if ((int) $currentSubscription->plan_id === (int) $plan->id) {
                return $this->subscriptionService->changeBillingCycle($currentSubscription, $interval);
            }

            return $this->subscriptionService->swapPlan($currentSubscription, $plan, $interval);
        }

        return $this->subscriptionService->subscribe($user, $plan, $interval, $paymentMethod);
    }
}
