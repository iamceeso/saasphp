<?php

return [
    'enabled' => env('BILLING_ENABLED', true),

    'navigation' => [
        'enabled' => env('BILLING_NAV_ENABLED', true),
        'show_pricing' => env('BILLING_NAV_SHOW_PRICING', true),
        'show_subscriptions' => env('BILLING_NAV_SHOW_SUBSCRIPTIONS', true),
    ],
];
