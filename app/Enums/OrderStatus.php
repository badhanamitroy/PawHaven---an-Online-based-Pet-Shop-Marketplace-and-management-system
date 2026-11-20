<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Processing => 'Processing',
            self::Shipped => 'Dispatched / In Delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tailwind classes for displaying this status as a badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            self::Processing => 'bg-blue-50 text-blue-800 ring-blue-600/20',
            self::Shipped => 'bg-purple-50 text-purple-800 ring-purple-600/20',
            self::Delivered => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
            self::Cancelled => 'bg-red-50 text-red-800 ring-red-600/20',
        };
    }

    /**
     * Check if status can transition to the given next status.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($this === self::Cancelled || $this === self::Delivered) {
            return false;
        }

        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Cancelled], true),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled], true),
            self::Shipped => in_array($next, [self::Delivered, self::Cancelled], true),
        };
    }
}
