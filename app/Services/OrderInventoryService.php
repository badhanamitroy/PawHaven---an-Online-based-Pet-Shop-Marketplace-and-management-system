<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

class OrderInventoryService
{
    public function restoreReservedInventory(Order $order): void
    {
        if ($order->inventory_reserved_at === null) {
            return;
        }

        $items = $order->items()->whereNotNull('product_id')->get();

        foreach ($items as $item) {
            Product::query()
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->increment('stock_quantity', $item->quantity);
        }

        $order->inventory_reserved_at = null;
        $order->save();
    }
}
