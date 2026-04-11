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

        if (!$signature) {
            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            $this->webhookService->handleWebhook($payload, $signature);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
