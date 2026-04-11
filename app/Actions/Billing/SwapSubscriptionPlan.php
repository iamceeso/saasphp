<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\SubscriptionService;

class SwapSubscriptionPlan
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(
        CustomerSubscription $subscription,
        SubscriptionPlan $plan,
        string $interval,
        bool $prorateCosts = true
    ): CustomerSubscription {
        return $this->subscriptionService->swapPlan($subscription, $plan, $interval, $prorateCosts);
    }
}
