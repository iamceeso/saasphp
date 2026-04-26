<?php

namespace App\Enums;

/**
 * Enum SmsProviders
 *
 * Represents supported SMS provider integrations used in the application.
 *
 *
 * @method static static VONAGE() Vonage SMS API
 * @method static static AFRICA_TALKING() Africa's Talking SMS gateway
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
