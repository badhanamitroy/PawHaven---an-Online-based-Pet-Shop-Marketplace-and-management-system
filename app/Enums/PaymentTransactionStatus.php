<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted for verification',
            self::Verified => 'Verified / collected',
            self::Failed => 'Failed',
            self::Reversed => 'Reversed',
            self::Refunded => 'Refunded',
        };
    }
}
