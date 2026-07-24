<?php

namespace App\Services\Billing\Concerns;

use Stripe\StripeClient;

trait HasStripeClient
{
    private ?StripeClient $stripe = null;

    protected function getStripeClient(): StripeClient
    {
        if ($this->stripe === null) {
            $secret = config('services.stripe.secret');

            if (! $secret) {
                throw new \InvalidArgumentException(
                    'Stripe secret key is not configured. Please set STRIPE_SECRET_KEY in your .env file.'
                );
            }

            $this->stripe = new StripeClient($secret);
        }

        return $this->stripe;
    }
}
