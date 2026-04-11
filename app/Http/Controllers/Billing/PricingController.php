<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\CustomerSubscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function show()
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->with(['prices' => fn ($q) => $q->active(), 'features'])
            ->get();

        $userSubscription = null;
        if (auth()->check()) {
            $userSubscription = auth()->user()->getCurrentSubscription();
        }

        return Inertia::render('Billing/Pricing', [
            'plans' => $plans,
            'userSubscription' => $userSubscription,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'interval' => 'required|in:monthly,annually',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $price = $plan->prices()
            ->where('interval', $request->interval)
            ->where('is_active', true)
            ->firstOrFail();

        $user = auth()->user();

        return Inertia::render('Billing/Checkout', [
            'plan' => $plan,
            'price' => $price,
            'interval' => $request->interval,
            'clientSecret' => null,
            'publishableKey' => config('services.stripe.public'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $this->authorize('create', CustomerSubscription::class);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'interval' => 'required|in:monthly,annually',
            'payment_method' => 'required|string',
        ]);

        try {
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            
            $subscription = CustomerSubscription::create([
                'user_id' => auth()->id(),
                'subscription_plan_id' => $plan->id,
                'interval' => $request->interval,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $request->interval === 'monthly' ? now()->addMonth() : now()->addYear(),
            ]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription,
                'redirect' => route('subscriptions.show', $subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
