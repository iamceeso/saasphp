<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookService $webhookService
    ) {}

    public function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $event = json_decode($payload, true);

        if (!$signature) {
            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            $this->webhookService->handleWebhook($payload, $signature);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook rejected', [
                'error' => $e->getMessage(),
                'event_id' => data_get($event, 'id'),
                'event_type' => data_get($event, 'type'),
            ]);

            return response()->json(['error' => 'Webhook could not be processed.'], 422);
        }
    }
}
