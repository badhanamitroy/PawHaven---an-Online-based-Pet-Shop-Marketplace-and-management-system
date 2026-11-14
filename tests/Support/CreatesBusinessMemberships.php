<?php

namespace Tests\Support;

use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\User;

trait CreatesBusinessMemberships
{
    protected function grantCompanyWideShopAccess(User $user, string $membershipRole = 'owner'): void
    {
        $company = Company::query()
            ->withoutGlobalScopes()
            ->where('slug', 'pawhaven')
            ->firstOrFail();

        BusinessMembership::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'branch_id' => null,
            ],
            [
                'role' => $membershipRole,
                'is_active' => true,
            ],
        );
    }
}
