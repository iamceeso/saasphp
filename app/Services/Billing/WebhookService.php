<?php

namespace App\Services\Billing;

use App\Models\WebhookLog;
use App\Models\BillingEvent;
use App\Models\CustomerSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Event;
use Stripe\Webhook;
use Exception;

class WebhookService
{
    public function handleWebhook(string $payload, string $signature): WebhookLog
    {
        $secret = config('services.stripe.webhook_secret');
        if (!$secret) {
            throw new \InvalidArgumentException(
                'Stripe webhook secret is not configured. Please set STRIPE_WEBHOOK_SECRET in your .env file.'
            );
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (Exception $e) {
            throw new Exception("Webhook signature verification failed: {$e->getMessage()}");
        }

        $log = WebhookLog::firstOrCreate(
            ['stripe_event_id' => $event->id],
            [
                'event_type' => $event->type,
                'payload' => json_encode($event->data),
                'processed' => false,
            ]
        );

        if ($log->processed) {
            return $log;
        }

        try {
            $this->processEvent($event);
            $log->markAsProcessed();
        } catch (Exception $e) {
            $log->recordAttempt($e->getMessage());
        }

        return $log;
    }

    private function processEvent(Event $event): void
    {
        match ($event->type) {
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handlePaymentFailed($event),
            'customer.subscription.trial_will_end' => $this->handleTrialWillEnd($event),
            default => null,
        };
    }

    private function handleSubscriptionCreated(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $this->syncSubscriptionState($stripeSubscription);
    }

    private function handleSubscriptionUpdated(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $this->syncSubscriptionState($stripeSubscription);
    }

    private function handleSubscriptionDeleted(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = CustomerSubscription::where(
            'stripe_subscription_id',
            $stripeSubscription->id
        )->first();

        if (! $subscription) {
            return;
        }

        DB::transaction(function () use ($subscription, $stripeSubscription) {
            $subscription->update([
                'current_subscription_key' => null,
                'status' => 'canceled',
                'canceled_at' => $this->timestampToCarbon($stripeSubscription->canceled_at) ?? now(),
                'ended_at' => $this->timestampToCarbon($stripeSubscription->ended_at) ?? now(),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancel_at_period_end' => false,
                ]),
            ]);

            BillingEvent::create([
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'event_type' => 'subscription.deleted',
                'payload' => json_encode($stripeSubscription),
                'processed_at' => now(),
            ]);
        });
    }

    private function handlePaymentSucceeded(Event $event): void
    {
        $invoice = $event->data->object;

        if ($invoice->subscription) {
            $subscription = CustomerSubscription::where(
                'stripe_subscription_id',
                $invoice->subscription
            )->first();

            if (! $subscription) {
                return;
            }

            DB::transaction(function () use ($subscription, $invoice) {
                $subscription->update([
                    'status' => 'active',
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'cancel_at_period_end' => false,
                    ]),
                ]);

                BillingEvent::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'event_type' => 'payment.succeeded',
                    'payload' => json_encode($invoice),
                    'processed_at' => now(),
                ]);
            });
        }
    }

    private function handlePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;

        if ($invoice->subscription) {
            $subscription = CustomerSubscription::where(
                'stripe_subscription_id',
                $invoice->subscription
            )->first();

            if (! $subscription) {
                return;
            }

            DB::transaction(function () use ($subscription, $invoice) {
                $subscription->update(['status' => 'past_due']);

                BillingEvent::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'event_type' => 'payment.failed',
                    'payload' => json_encode($invoice),
                    'processed_at' => now(),
                ]);
            });
        }
    }

    private function handleTrialWillEnd(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = CustomerSubscription::where(
            'stripe_subscription_id',
            $stripeSubscription->id
        )->first();

        if (! $subscription) {
            return;
        }

        BillingEvent::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'event_type' => 'trial.will_end',
            'payload' => json_encode($stripeSubscription),
            'processed_at' => now(),
        ]);
    }

    private function syncSubscriptionState($stripeSubscription): void
    {
        $subscription = CustomerSubscription::where(
            'stripe_subscription_id',
            $stripeSubscription->id
        )->first();

        if (!$subscription) {
            return;
        }

        $price = $stripeSubscription->items->data[0]->price ?? null;
        if (!$price) {
            return;
        }

        $subscription->update([
            'current_subscription_key' => $this->resolveCurrentSubscriptionKey(
                $subscription->user_id,
                $stripeSubscription->status,
                $stripeSubscription->ended_at,
            ),
            'status' => $stripeSubscription->status,
            'current_period_start' => $this->timestampToCarbon($stripeSubscription->current_period_start),
            'current_period_end' => $this->timestampToCarbon($stripeSubscription->current_period_end),
            'trial_ends_at' => $this->timestampToCarbon($stripeSubscription->trial_end),
            'canceled_at' => $this->timestampToCarbon($stripeSubscription->canceled_at),
            'ended_at' => $this->timestampToCarbon($stripeSubscription->ended_at),
            'metadata' => array_merge($subscription->metadata ?? [], [
                'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
            ]),
        ]);
    }

    public function retryFailedWebhooks(int $maxRetries = 3): void
    {
        $failedLogs = WebhookLog::where('processed', false)
            ->where('attempts', '<', $maxRetries)
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($failedLogs as $log) {
            try {
                $event = (object) json_decode($log->payload, true);
                $event->type = $log->event_type;

                $this->processEvent($event);
                $log->markAsProcessed();
            } catch (Exception $e) {
                $log->recordAttempt($e->getMessage());
            }
        }
    }

    private function timestampToCarbon(null|int|string $timestamp): ?Carbon
    {
        if (blank($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function resolveCurrentSubscriptionKey(int $userId, string $status, mixed $endedAt = null): ?string
    {
        if (in_array($status, CustomerSubscription::CURRENT_SLOT_STATUSES, true) && blank($endedAt)) {
            return "user:{$userId}";
        }

        return null;
    }
}
