<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Services\BusinessAccessService;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        $access = app(BusinessAccessService::class);

        return $access->isInitialSetupAdmin($user)
            || $access->activeMemberships($user)->contains(fn ($membership): bool =>
                in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
            );
    }

    public function view(User $user, Company $company): bool
    {
        return app(BusinessAccessService::class)->canAccessCompany($user, $company->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            && \Illuminate\Support\Facades\Schema::hasTable('companies')
            && Company::query()->withoutGlobalScopes()->doesntExist();
    }

    public function update(User $user, Company $company): bool
    {
        return app(BusinessAccessService::class)->canManageCompany($user, $company->id);
    }
}
