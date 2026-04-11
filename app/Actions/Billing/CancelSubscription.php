<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Services\Billing\SubscriptionService;

class CancelSubscription
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(CustomerSubscription $subscription, bool $immediately = false): void
    {
        $this->subscriptionService->cancel($subscription, $immediately);
    }
}
