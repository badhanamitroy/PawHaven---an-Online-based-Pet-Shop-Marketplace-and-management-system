<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

class OrderInventoryService
{
    public function __construct(private readonly InventoryMovementService $inventory) {}

    public function restoreReservedInventory(Order $order, User $actor): void
    {
        $this->inventory->releaseForCancellation($order, $actor);
    }
}
