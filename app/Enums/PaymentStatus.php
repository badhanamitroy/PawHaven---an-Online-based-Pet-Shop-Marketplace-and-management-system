<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Collection',
            self::Paid => 'Paid (Cash on Delivery)',
            self::Refunded => 'Refunded',
        };
    }
}
