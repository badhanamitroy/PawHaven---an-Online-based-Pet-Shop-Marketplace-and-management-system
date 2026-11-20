<?php

namespace App\Enums;

enum ProductReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved for physical return',
            self::Rejected => 'Rejected',
            self::Received => 'Received and classified',
        };
    }
}
