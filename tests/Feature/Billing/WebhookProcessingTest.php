<?php

namespace Tests\Feature\Billing;

use App\Models\CustomerSubscription;
use App\Models\WebhookLog;
use App\Services\Billing\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WebhookControllerTest only covers transport concerns (signature, replay,
 * unknown-subscription rejection). These tests exercise the actual state
 * mutations WebhookService performs once a webhook matches a real local
 * subscription — the part of the billing system Stripe events actually drive.
 */
class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
    }

    public function test_subscription_created_webhook_syncs_local_subscription_state(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_created_1',
            'status' => 'incomplete',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $newPeriodStart = now()->addDay()->timestamp;
        $newPeriodEnd = now()->addMonth()->timestamp;

        $this->dispatchWebhook('evt_sub_created', 'customer.subscription.created', $this->stripeSubscriptionObject([
            'id' => 'sub_created_1',
            'status' => 'active',
            'current_period_start' => $newPeriodStart,
            'current_period_end' => $newPeriodEnd,
        ]))->assertOk();

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertSame($newPeriodStart, $subscription->current_period_start->timestamp);
        $this->assertSame($newPeriodEnd, $subscription->current_period_end->timestamp);
        $this->assertSame('user:'.$subscription->user_id, $subscription->current_subscription_key);
    }

    public function test_subscription_updated_webhook_syncs_local_subscription_state(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_updated_1',
            'status' => 'active',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $this->dispatchWebhook('evt_sub_updated', 'customer.subscription.updated', $this->stripeSubscriptionObject([
            'id' => 'sub_updated_1',
            'status' => 'past_due',
        ]))->assertOk();

        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_subscription_updated_webhook_clears_current_slot_when_subscription_ends(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_updated_ended',
            'status' => 'active',
            'metadata' => ['provider' => 'stripe'],
            'current_subscription_key' => 'user:1',
        ]);

        $endedAt = now()->timestamp;

        $this->dispatchWebhook('evt_sub_updated_ended', 'customer.subscription.updated', $this->stripeSubscriptionObject([
            'id' => 'sub_updated_ended',
            'status' => 'incomplete_expired',
            'ended_at' => $endedAt,
        ]))->assertOk();

        $subscription->refresh();

        $this->assertSame('incomplete_expired', $subscription->status);
        $this->assertNull($subscription->current_subscription_key);
    }

    public function test_subscription_deleted_webhook_marks_subscription_canceled(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_deleted_1',
            'status' => 'active',
            'current_subscription_key' => 'user:1',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $canceledAt = now()->timestamp;
        $endedAt = now()->timestamp;

        $this->dispatchWebhook('evt_sub_deleted', 'customer.subscription.deleted', [
            'id' => 'sub_deleted_1',
            'object' => 'subscription',
            'canceled_at' => $canceledAt,
            'ended_at' => $endedAt,
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame('canceled', $subscription->status);
        $this->assertNull($subscription->current_subscription_key);
        $this->assertSame($canceledAt, $subscription->canceled_at->timestamp);
        $this->assertSame($endedAt, $subscription->ended_at->timestamp);
        $this->assertDatabaseHas('billing_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'subscription.deleted',
        ]);
    }

    public function test_payment_succeeded_webhook_reactivates_subscription_and_logs_billing_event(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_payment_ok',
            'status' => 'past_due',
            'metadata' => ['provider' => 'stripe', 'cancel_at_period_end' => true],
        ]);

        $this->dispatchWebhook('evt_payment_succeeded', 'invoice.payment_succeeded', [
            'id' => 'in_test_1',
            'object' => 'invoice',
            'subscription' => 'sub_payment_ok',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertFalse((bool) data_get($subscription->metadata, 'cancel_at_period_end'));
        $this->assertDatabaseHas('billing_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'payment.succeeded',
        ]);
    }

    public function test_payment_failed_webhook_marks_subscription_past_due(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_payment_failed',
            'status' => 'active',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $this->dispatchWebhook('evt_payment_failed', 'invoice.payment_failed', [
            'id' => 'in_test_2',
            'object' => 'invoice',
            'subscription' => 'sub_payment_failed',
        ])->assertOk();

        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertDatabaseHas('billing_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'payment.failed',
        ]);
    }

    public function test_trial_will_end_webhook_records_billing_event_without_changing_status(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_trial_ending',
            'status' => 'trialing',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $this->dispatchWebhook('evt_trial_will_end', 'customer.subscription.trial_will_end', $this->stripeSubscriptionObject([
            'id' => 'sub_trial_ending',
            'status' => 'trialing',
        ]))->assertOk();

        $this->assertSame('trialing', $subscription->fresh()->status);
        $this->assertDatabaseHas('billing_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'trial.will_end',
        ]);
    }

    public function test_retry_failed_webhooks_reprocesses_a_pending_log_against_current_local_state(): void
    {
        $subscription = CustomerSubscription::factory()->create([
            'stripe_subscription_id' => 'sub_retry_ok',
            'status' => 'incomplete',
            'metadata' => ['provider' => 'stripe'],
        ]);

        $payload = [
            'id' => 'evt_retry_ok',
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => $this->stripeSubscriptionObject([
                    'id' => 'sub_retry_ok',
                    'status' => 'active',
                ]),
            ],
        ];

        $log = WebhookLog::create([
            'stripe_event_id' => 'evt_retry_ok',
            'event_type' => 'customer.subscription.updated',
            'payload' => $payload,
            'processed' => false,
            'attempts' => 1,
            'error' => 'previous attempt failed',
        ]);

        app(WebhookService::class)->retryFailedWebhooks();

        $log->refresh();
        $this->assertTrue($log->processed);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_retry_failed_webhooks_records_another_failed_attempt_when_still_unresolvable(): void
    {
        $payload = [
            'id' => 'evt_retry_still_broken',
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => $this->stripeSubscriptionObject([
                    'id' => 'sub_does_not_exist_locally',
                ]),
            ],
        ];

        $log = WebhookLog::create([
            'stripe_event_id' => 'evt_retry_still_broken',
            'event_type' => 'customer.subscription.updated',
            'payload' => $payload,
            'processed' => false,
            'attempts' => 1,
        ]);

        app(WebhookService::class)->retryFailedWebhooks();

        $log->refresh();
        $this->assertFalse($log->processed);
        $this->assertSame(2, $log->attempts);
        $this->assertStringContainsString('unknown local subscription', $log->error);
    }

    public function test_retry_failed_webhooks_skips_logs_that_already_exhausted_max_retries(): void
    {
        $log = WebhookLog::create([
            'stripe_event_id' => 'evt_exhausted',
            'event_type' => 'customer.subscription.updated',
            'payload' => [
                'id' => 'evt_exhausted',
                'object' => 'event',
                'type' => 'customer.subscription.updated',
                'data' => ['object' => $this->stripeSubscriptionObject(['id' => 'sub_whatever'])],
            ],
            'processed' => false,
            'attempts' => 3,
        ]);

        app(WebhookService::class)->retryFailedWebhooks(maxRetries: 3);

        $log->refresh();
        $this->assertFalse($log->processed);
        $this->assertSame(3, $log->attempts);
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeSubscriptionObject(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_test_default',
            'object' => 'subscription',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_end' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'cancel_at_period_end' => false,
            'items' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'si_test',
                        'object' => 'subscription_item',
                        'price' => [
                            'id' => 'price_test',
                            'object' => 'price',
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function dispatchWebhook(string $eventId, string $type, array $object)
    {
        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);

        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }
}
