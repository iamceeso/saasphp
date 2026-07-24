<?php

namespace Tests\Feature\Billing;

use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $payload = $this->stripePayload('evt_bad_signature', 'ping');

        $this->postJson(route('webhooks.stripe'), json_decode($payload, true), [
            'Stripe-Signature' => 't='.now()->timestamp.',v1=invalid',
        ])->assertStatus(422)
            ->assertJson(['error' => 'Webhook could not be processed.']);

        $this->assertDatabaseMissing('webhook_logs', [
            'stripe_event_id' => 'evt_bad_signature',
        ]);
    }

    public function test_rejects_malformed_signed_payload(): void
    {
        $payload = '{"id":';

        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $this->signatureFor($payload),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertStatus(422)
            ->assertJson(['error' => 'Webhook could not be processed.']);
    }

    public function test_replayed_event_reuses_existing_processed_log(): void
    {
        $payload = $this->stripePayload('evt_replayed', 'ping');
        $signature = $this->signatureFor($payload);

        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertOk();

        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame(1, WebhookLog::where('stripe_event_id', 'evt_replayed')->count());

        $log = WebhookLog::where('stripe_event_id', 'evt_replayed')->firstOrFail();
        $this->assertTrue($log->processed);
        $this->assertSame(0, $log->attempts);
    }

    private function stripePayload(string $id, string $type): string
    {
        return json_encode([
            'id' => $id,
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'obj_test',
                    'object' => 'event',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signatureFor(string $payload): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

        return "t={$timestamp},v1={$signature}";
    }
}
