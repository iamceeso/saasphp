<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\ChangeSubscriptionBillingCycle;
use App\Actions\Billing\ResumeSubscription;
use App\Actions\Billing\SwapSubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private SwapSubscriptionPlan $swapSubscriptionPlan,
        private ChangeSubscriptionBillingCycle $changeSubscriptionBillingCycle,
        private CancelSubscription $cancelSubscription,
        private ResumeSubscription $resumeSubscription
    ) {}

    public function index()
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        $this->subscriptionService->normalizeCurrentSubscriptions(auth()->user());

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
            $updated = $this->swapSubscriptionPlan->handle(
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
        } catch (\Throwable $e) {
            Log::warning('Subscription plan swap failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_id' => $request->input('plan_id'),
                'interval' => $request->input('interval'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'We could not update your subscription plan right now. Please try again.',
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
            $updated = $this->changeSubscriptionBillingCycle->handle(
                $subscription,
                $request->interval
            );

            return response()->json([
                'success' => true,
                'subscription' => $updated,
                'message' => 'Billing cycle updated successfully',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription billing cycle change failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'interval' => $request->input('interval'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'We could not update your billing cycle right now. Please try again.',
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
            $this->cancelSubscription->handle(
                $subscription,
                $request->boolean('immediately', false)
            );

            return response()->json([
                'success' => true,
                'message' => $request->boolean('immediately', false)
                    ? 'Subscription canceled immediately'
                    : 'Subscription will be canceled at the end of the billing period',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription cancellation failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'immediately' => $request->boolean('immediately', false),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'We could not cancel your subscription right now. Please try again.',
            ], 422);
        }
    }

    public function resume(CustomerSubscription $subscription)
    {
        $this->authorize('resume', $subscription);

        try {
            $updated = $this->resumeSubscription->handle($subscription);

            return response()->json([
                'success' => true,
                'subscription' => $updated,
                'message' => 'Subscription resumed successfully',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Subscription resume failed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'We could not resume your subscription right now. Please try again.',
            ], 422);
        }
    }

    public function invoices(CustomerSubscription $subscription)
    {
        $this->authorize('viewInvoices', $subscription);

        $subscription->loadMissing('user', 'plan');
        $invoices = collect();
        $upcomingInvoice = null;

        try {
            $provider = data_get($subscription->metadata, 'provider');
            $hasStripeCustomer = !empty($subscription->user?->stripe_id);
            $hasStripeSubscription = !empty($subscription->stripe_subscription_id);

            if ($provider !== 'local' && $hasStripeCustomer && $hasStripeSubscription) {
                $invoices = collect($this->subscriptionService->getStripeInvoices($subscription));
                $upcomingInvoice = $this->subscriptionService->getUpcomingStripeInvoice($subscription);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Stripe invoices', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        $invoicePayload = $invoices->map(function ($invoice) {
            $stripeInvoice = method_exists($invoice, 'asStripeInvoice')
                ? $invoice->asStripeInvoice()
                : $invoice;

            return [
                'id' => data_get($stripeInvoice, 'id'),
                'number' => data_get($stripeInvoice, 'number'),
                'amount' => (int) data_get($stripeInvoice, 'amount_paid', data_get($stripeInvoice, 'total', 0)),
                'subtotal' => (int) data_get($stripeInvoice, 'subtotal', 0),
                'total' => (int) data_get($stripeInvoice, 'total', 0),
                'amount_due' => (int) data_get($stripeInvoice, 'amount_due', 0),
                'amount_paid' => (int) data_get($stripeInvoice, 'amount_paid', 0),
                'status' => data_get($stripeInvoice, 'status', 'draft'),
                'created' => (int) data_get($stripeInvoice, 'created', now()->timestamp),
                'paid_at' => data_get($stripeInvoice, 'status_transitions.paid_at'),
                'period_start' => data_get($stripeInvoice, 'period_start'),
                'period_end' => data_get($stripeInvoice, 'period_end'),
                'attempt_count' => (int) data_get($stripeInvoice, 'attempt_count', 0),
                'description' => data_get($stripeInvoice, 'description'),
                'invoice_pdf' => data_get($stripeInvoice, 'invoice_pdf'),
                'hosted_invoice_url' => data_get($stripeInvoice, 'hosted_invoice_url'),
                'currency' => strtoupper((string) data_get($stripeInvoice, 'currency', 'usd')),
            ];
        })->values();

        $upcomingInvoicePayload = $upcomingInvoice ? [
            'amount' => (int) data_get($upcomingInvoice, 'amount_due', data_get($upcomingInvoice, 'total', 0)),
            'currency' => strtoupper((string) data_get($upcomingInvoice, 'currency', 'usd')),
            'next_payment_attempt' => data_get($upcomingInvoice, 'next_payment_attempt'),
            'period_end' => data_get($upcomingInvoice, 'period_end'),
        ] : null;

        return Inertia::render('Billing/Invoices', [
            'subscription' => $subscription,
            'invoices' => $invoicePayload,
            'upcomingInvoice' => $upcomingInvoicePayload,
        ]);
    }
}
