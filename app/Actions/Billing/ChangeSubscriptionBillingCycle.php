<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Services\Billing\SubscriptionService;

class ChangeSubscriptionBillingCycle
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(CustomerSubscription $subscription, string $interval): CustomerSubscription
    {
        return $this->subscriptionService->changeBillingCycle($subscription, $interval);
    }
}
