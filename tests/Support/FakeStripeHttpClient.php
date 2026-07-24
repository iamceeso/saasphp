<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * A minimal fake of Stripe's HTTP transport layer.
 *
 * The app talks to Stripe through the raw `Stripe\StripeClient` SDK, which
 * uses its own cURL-based HTTP client rather than Laravel's HTTP facade, so
 * `Http::fake()` cannot intercept it. Stripe's own supported way to test
 * against the SDK without hitting the network is to swap the transport layer
 * via `\Stripe\ApiRequestor::setHttpClient()` with something implementing
 * `ClientInterface`. This fake returns canned, minimally-valid Stripe object
 * payloads for each endpoint the app actually calls, and records every
 * request so tests can assert on what was sent.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, params: array}> */
    public array $requests = [];

    private int $sequence = 0;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $path = parse_url($absUrl, PHP_URL_PATH) ?: '';
        $body = $this->respond($method, $path, $params);

        return [json_encode($body, JSON_THROW_ON_ERROR), 200, []];
    }

    private function respond(string $method, string $path, array $params): array
    {
        $id = fn (string $prefix) => $prefix.'_fake_'.(++$this->sequence);

        return match (true) {
            str_contains($path, '/v1/payment_methods/') && str_ends_with($path, '/attach') => [
                'id' => basename(str_replace('/attach', '', $path)),
                'object' => 'payment_method',
                'customer' => $params['customer'] ?? null,
            ],
            preg_match('#/v1/customers/[^/]+$#', $path) === 1 => [
                'id' => basename($path),
                'object' => 'customer',
                'invoice_settings' => [
                    'default_payment_method' => $params['invoice_settings']['default_payment_method'] ?? null,
                ],
            ],
            $path === '/v1/customers' => [
                'id' => $id('cus'),
                'object' => 'customer',
                'name' => $params['name'] ?? null,
                'email' => $params['email'] ?? null,
            ],
            $path === '/v1/products' => [
                'id' => $id('prod'),
                'object' => 'product',
                'name' => $params['name'] ?? null,
            ],
            $path === '/v1/prices' => [
                'id' => $id('price'),
                'object' => 'price',
                'unit_amount' => $params['unit_amount'] ?? 0,
                'currency' => $params['currency'] ?? 'usd',
                'recurring' => $params['recurring'] ?? null,
            ],
            $path === '/v1/subscriptions' => $this->subscriptionObject($id, $params),
            preg_match('#/v1/subscriptions/[^/]+$#', $path) === 1 && $method === 'post' => array_merge(
                $this->subscriptionObject($id, $params),
                ['id' => basename($path)],
            ),
            default => ['id' => $id('obj'), 'object' => 'unknown'],
        };
    }

    private function subscriptionObject(\Closure $id, array $params): array
    {
        $now = now();
        $priceId = data_get($params, 'items.0.price', 'price_fake_default');

        return [
            'id' => $id('sub'),
            'object' => 'subscription',
            'customer' => $params['customer'] ?? null,
            'status' => 'active',
            'current_period_start' => $now->timestamp,
            'current_period_end' => $now->copy()->addMonth()->timestamp,
            'trial_end' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'cancel_at_period_end' => false,
            'items' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'si_fake_1',
                        'object' => 'subscription_item',
                        'price' => ['id' => $priceId, 'object' => 'price'],
                    ],
                ],
            ],
            'latest_invoice' => [
                'id' => 'in_fake_1',
                'object' => 'invoice',
                'payment_intent' => [
                    'id' => 'pi_fake_1',
                    'object' => 'payment_intent',
                    'client_secret' => 'pi_fake_1_secret_test',
                    'status' => 'succeeded',
                ],
            ],
        ];
    }
}
