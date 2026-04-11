<?php

namespace App\Actions\Billing;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($user, $plan, $interval, $paymentMethod) {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentSubscription = $this->subscriptionService->normalizeCurrentSubscriptions($lockedUser);

            if ($currentSubscription instanceof CustomerSubscription) {
                if ((int) $currentSubscription->plan_id === (int) $plan->id && $currentSubscription->interval === $interval) {
                    return $currentSubscription->refresh();
                }

                if ((int) $currentSubscription->plan_id === (int) $plan->id) {
                    return $this->subscriptionService->changeBillingCycle($currentSubscription, $interval);
                }

                return $this->subscriptionService->swapPlan($currentSubscription, $plan, $interval);
            }

            return $this->subscriptionService->subscribe($lockedUser, $plan, $interval, $paymentMethod);
        }, 3);
    }
}
