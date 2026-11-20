<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Opening = 'opening';
    case PosReservation = 'pos_reservation';
    case OnlineReservation = 'online_reservation';
    case CancellationRelease = 'cancellation_release';
    case ReturnReceived = 'return_received';
    case ReturnRestock = 'return_restock';
    case ReturnDamaged = 'return_damaged';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening balance',
            self::PosReservation => 'POS reservation',
            self::OnlineReservation => 'Online reservation',
            self::CancellationRelease => 'Cancellation release',
            self::ReturnReceived => 'Customer return physically received',
            self::ReturnRestock => 'Resellable return restocked',
            self::ReturnDamaged => 'Damaged/non-resellable return',
            self::ManualAdjustment => 'Manual adjustment',
        };
    }
}
