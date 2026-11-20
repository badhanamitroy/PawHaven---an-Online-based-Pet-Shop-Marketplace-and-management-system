<?php

namespace App\Enums;

enum PaymentRefundStatus: string
{
    case Requested = 'requested';
    case Refunded = 'refunded';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Refund requested',
            self::Refunded => 'Refund completed',
            self::Rejected => 'Refund rejected',
        };
    }
}
