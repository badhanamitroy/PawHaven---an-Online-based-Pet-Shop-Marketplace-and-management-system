<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;
use App\Services\BusinessAccessService;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return app(BusinessAccessService::class)->activeMemberships($user)->contains(
            fn ($membership): bool => $membership->role === BusinessAccessService::OWNER
                && $membership->branch_id === null
        );
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $this->viewAny($user)
            && app(BusinessAccessService::class)->canAccessCompany($user, (int) $category->company_id);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
