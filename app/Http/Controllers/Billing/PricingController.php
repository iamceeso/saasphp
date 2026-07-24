<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SubscribeToPlan;
use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Class PricingController.
 */
class PricingController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private SubscribeToPlan $subscribeToPlan
    ) {}

    public function show()
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->with(['prices' => fn ($q) => $q->active(), 'features'])
            ->get();

        $userSubscription = null;
        if (Auth::check()) {
            $userSubscription = $this->subscriptionService->getCurrentSubscriptionForDisplay(Auth::user());
        }

        return Inertia::render('Billing/Pricing', [
            'plans' => $plans,
            'userSubscription' => $userSubscription,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => [
                'required',
                Rule::exists('subscription_plans', 'id')->where('is_active', true),
            ],
            'interval' => 'required|in:monthly,annually',
        ]);

        $plan = SubscriptionPlan::active()->findOrFail($request->plan_id);
        $price = $plan->prices()
            ->where('interval', $request->interval)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('Billing/Checkout', [
            'plan' => $plan,
            'price' => $price,
            'interval' => $request->interval,
            'publishableKey' => config('services.stripe.public'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $this->authorize('create', CustomerSubscription::class);
        $request->validate([
            'plan_id' => [
                'required',
                Rule::exists('subscription_plans', 'id')->where('is_active', true),
            ],
            'interval' => 'required|in:monthly,annually',
            'payment_method' => ['nullable', 'string', 'regex:/^pm_[A-Za-z0-9]+$/'],
        ]);

        try {
            $plan = SubscriptionPlan::active()->findOrFail($request->plan_id);
            $price = $plan->prices()
                ->where('interval', $request->interval)
                ->where('is_active', true)
                ->firstOrFail();

            $paymentMethod = $request->filled('payment_method')
                ? (string) $request->payment_method
                : null;
            $user = Auth::user();

            if ((int) $price->amount === 0) {
                $result = $this->subscribeToPlan->handle(
                    $user,
                    $plan,
                    $request->interval,
                    null
                );
            } elseif ($paymentMethod) {
                $result = $this->subscribeToPlan->handle(
                    $user,
                    $plan,
                    $request->interval,
                    $paymentMethod
                );
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid Stripe payment method. Please refresh checkout and try again.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'subscription' => $result['subscription'],
                'redirect' => route('subscriptions.show', $result['subscription']),
                'clientSecret' => $result['payment_intent_client_secret'],
                'paymentIntentStatus' => $result['payment_intent_status'],
                'requiresAction' => $result['requires_action'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription checkout failed', [
                'user_id' => Auth::id(),
                'plan_id' => $request->input('plan_id'),
                'interval' => $request->input('interval'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'We could not process your subscription right now. Please try again.',
            ], 422);
        }
    }
}
