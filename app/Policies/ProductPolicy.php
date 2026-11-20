<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Services\BusinessAccessService;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user instanceof User
            && app(BusinessAccessService::class)->activeMemberships($user)->contains(
                fn ($membership): bool => in_array(
                    $membership->role,
                    [BusinessAccessService::OWNER, BusinessAccessService::MANAGER, BusinessAccessService::EMPLOYEE_SELLER],
                    true,
                )
            );
    }

    public function view(?User $user, Product $product): bool
    {
        if ($product->isPurchasable()) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return app(BusinessAccessService::class)->canAccessRecord(
            $user,
            $product->company_id,
            $product->branch_id,
        );
    }

    public function create(User $user): bool
    {
        return app(BusinessAccessService::class)->activeMemberships($user)->contains(
            fn ($membership): bool => $membership->role === BusinessAccessService::OWNER
                && $membership->branch_id === null
        );
    }

    public function update(User $user, Product $product): bool
    {
        return app(BusinessAccessService::class)->canManageProducts($user, $product->company_id)
            && app(BusinessAccessService::class)->canAccessRecord($user, $product->company_id, $product->branch_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return app(BusinessAccessService::class)->canManageProducts($user, $product->company_id)
            && app(BusinessAccessService::class)->canAccessRecord($user, $product->company_id, $product->branch_id);
    }
}
