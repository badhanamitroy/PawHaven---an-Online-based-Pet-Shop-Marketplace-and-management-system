<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\BusinessAccessService;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->isStaff() && app(BusinessAccessService::class)->activeMemberships($user)->contains(
            fn ($membership): bool => in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
        ))
            || $user->isSeller();
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isStaff()) {
            return app(BusinessAccessService::class)->canViewOrders(
                $user, $order->company_id, $order->branch_id,
            );
        }

        if ($order->seller_id !== null && $order->seller_id === $user->id) {
            return true;
        }

        return $order->user_id !== null && $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isStaff()) {
            return app(BusinessAccessService::class)->canManageOrders(
                $user, $order->company_id, $order->branch_id,
            );
        }

        return $order->seller_id !== null && $order->seller_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isStaff()) {
            return app(BusinessAccessService::class)->canManageOrders(
                $user, $order->company_id, $order->branch_id,
            );
        }

        if ($order->seller_id !== null && $order->seller_id === $user->id) {
            return true;
        }

        return $order->user_id !== null && $order->user_id === $user->id && $order->status === OrderStatus::Pending;
    }
}
