<?php

namespace App\Enums;

enum FulfilmentStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case Picking = 'picking';
    case ShortageReview = 'shortage_review';
    case Packed = 'packed';
    case ReadyForPickup = 'ready_for_pickup';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Dispatched = 'dispatched';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryFailed = 'delivery_failed';
    case ReturnedToSender = 'returned_to_sender';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingConfirmation => 'Awaiting confirmation',
            self::Confirmed => 'Confirmed',
            self::Picking => 'Picking',
            self::ShortageReview => 'Availability review',
            self::Packed => 'Packed',
            self::ReadyForPickup => 'Ready for pickup',
            self::ReadyForDispatch => 'Ready for dispatch',
            self::Dispatched => 'Dispatched',
            self::OutForDelivery => 'Out for delivery',
            self::DeliveryFailed => 'Delivery attempt failed',
            self::ReturnedToSender => 'Returned to sender — review required',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isPreDispatch(): bool
    {
        return in_array($this, [
            self::AwaitingConfirmation,
            self::Confirmed,
            self::Picking,
            self::ShortageReview,
            self::Packed,
            self::ReadyForPickup,
            self::ReadyForDispatch,
        ], true);
    }
}
