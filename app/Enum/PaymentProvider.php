<?php

namespace App\Enum;

enum PaymentProvider : string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal'; // We'll implement this in Part 3
    
    /**
     * Get all payment providers as an array for validation
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
