<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function index()
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        $subscriptions = auth()->user()
            ->subscriptions()
            ->with('plan.prices', 'plan.features')
            ->latest()
            ->paginate(15);

        return Inertia::render('Billing/Subscriptions', [
            'subscriptions' => $subscriptions,
        ]);
    }

    public function show(CustomerSubscription $subscription)
    {
        $this->authorize('view', $subscription);

        $subscription->load('plan.prices', 'plan.features', 'billingEvents');

        return Inertia::render('Billing/SubscriptionDetail', [
            'subscription' => $subscription,
            'availablePlans' => SubscriptionPlan::active()
                ->where('id', '!=', $subscription->plan_id)
                ->with(['prices' => fn ($q) => $q->active(), 'features'])
                ->get(),
        ]);
    }

    public function swapPlan(Request $request, CustomerSubscription $subscription)
    {
        $this->authorize('swapPlan', $subscription);

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'interval' => 'required|in:monthly,annually',
            'prorate' => 'boolean',
        ]);

        try {
            $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
            $updated = $this->subscriptionService->swapPlan(
                $subscription,
                $newPlan,
                $request->interval,
                $request->boolean('prorate', true)
            );

            return response()->json([
                'success' => true,
                'subscription' => $updated,
                'message' => 'Plan updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function changeBillingCycle(Request $request, CustomerSubscription $subscription)
    {
        $this->authorize('changeBillingCycle', $subscription);

        $request->validate([
            'interval' => 'required|in:monthly,annually',
        ]);

        try {
            $updated = $this->subscriptionService->changeBillingCycle(
                $subscription,
                $request->interval
            );

            return response()->json([
                'success' => true,
                'subscription' => $updated,
                'message' => 'Billing cycle updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, CustomerSubscription $subscription)
    {
        $this->authorize('cancel', $subscription);

        $request->validate([
            'immediately' => 'boolean',
        ]);

        try {
            $this->subscriptionService->cancel(
                $subscription,
                $request->boolean('immediately', false)
            );

            return response()->json([
                'success' => true,
                'message' => $request->boolean('immediately', false)
                    ? 'Subscription canceled immediately'
                    : 'Subscription will be canceled at the end of the billing period',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function resume(CustomerSubscription $subscription)
    {
        $this->authorize('resume', $subscription);

        try {
            $updated = $this->subscriptionService->resume($subscription);

            return response()->json([
                'success' => true,
                'subscription' => $updated,
                'message' => 'Subscription resumed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function invoices(CustomerSubscription $subscription)
    {
        $this->authorize('viewInvoices', $subscription);

        $invoices = $subscription->user->invoices()->get();

        return Inertia::render('Billing/Invoices', [
            'subscription' => $subscription,
            'invoices' => $invoices,
        ]);
    }
}
