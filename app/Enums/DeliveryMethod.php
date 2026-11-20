<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case BranchPickup = 'branch_pickup';
    case InternalDelivery = 'internal_delivery';
    case ExternalCourier = 'external_courier';

    public function label(): string
    {
        return match ($this) {
            self::BranchPickup => 'Branch pickup',
            self::InternalDelivery => 'Internal delivery',
            self::ExternalCourier => 'External courier',
        };
    }
}
