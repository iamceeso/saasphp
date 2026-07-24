<?php

namespace App\Enums;

/**
 * Supported SMS provider integrations.
 */
enum SmsProviders: string
{
    /**
     * Vonage SMS API
     */
    case VONAGE = 'vonage';

    /**
     * Africa's Talking SMS gateway
     */
    case AFRICA_TALKING = 'africa_talking';
}
