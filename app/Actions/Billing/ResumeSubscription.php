<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Services\Billing\SubscriptionService;

class ResumeSubscription
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(CustomerSubscription $subscription): CustomerSubscription
    {
        return $this->subscriptionService->resume($subscription);
    }
}
