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
    ): array {
        $selectedPrice = $plan->prices()
            ->where('interval', $interval)
            ->where('is_active', true)
            ->firstOrFail();

        $decision = DB::transaction(function () use ($user, $plan, $interval) {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentSubscription = $this->subscriptionService->normalizeCurrentSubscriptions($lockedUser);

            if (! $currentSubscription instanceof CustomerSubscription) {
                return [
                    'type' => 'subscribe',
                    'user_id' => $lockedUser->id,
                ];
            }

            if ((int) $currentSubscription->plan_id === (int) $plan->id && $currentSubscription->interval === $interval) {
                return [
                    'type' => 'reuse',
                    'subscription_id' => $currentSubscription->id,
                ];
            }

            if ((int) $currentSubscription->plan_id === (int) $plan->id) {
                return [
                    'type' => 'change_cycle',
                    'subscription_id' => $currentSubscription->id,
                ];
            }

            return [
                'type' => 'swap',
                'subscription_id' => $currentSubscription->id,
            ];
        }, 3);

        if ($decision['type'] === 'reuse') {
            return [
                'subscription' => CustomerSubscription::query()->findOrFail($decision['subscription_id'])->refresh(),
                'payment_intent_client_secret' => null,
                'payment_intent_status' => null,
                'requires_action' => false,
            ];
        }

        if ($decision['type'] === 'change_cycle') {
            return [
                'subscription' => $this->subscriptionService->changeBillingCycle(
                    CustomerSubscription::query()->findOrFail($decision['subscription_id']),
                    $interval
                ),
                'payment_intent_client_secret' => null,
                'payment_intent_status' => null,
                'requires_action' => false,
            ];
        }

        if ($decision['type'] === 'subscribe') {
            return $this->subscriptionService->subscribe(
                User::query()->findOrFail($decision['user_id']),
                $plan,
                $interval,
                $paymentMethod
            );
        }

        $currentSubscription = CustomerSubscription::query()->findOrFail($decision['subscription_id']);

        if ((int) $selectedPrice->amount === 0) {
            return [
                'subscription' => $this->subscriptionService->swapPlan(
                    $currentSubscription,
                    $plan,
                    $interval
                ),
                'payment_intent_client_secret' => null,
                'payment_intent_status' => null,
                'requires_action' => false,
            ];
        }

        if (data_get($currentSubscription->metadata, 'provider') === 'free') {
            if (! $paymentMethod) {
                throw new \InvalidArgumentException('A payment method is required to upgrade from a free plan.');
            }

            return $this->subscriptionService->replaceFreeSubscriptionWithPaidPlan(
                $currentSubscription,
                $plan,
                $interval,
                $paymentMethod
            );
        }

        return [
            'subscription' => $this->subscriptionService->swapPlan(
                $currentSubscription,
                $plan,
                $interval
            ),
            'payment_intent_client_secret' => null,
            'payment_intent_status' => null,
            'requires_action' => false,
        ];
    }
}
