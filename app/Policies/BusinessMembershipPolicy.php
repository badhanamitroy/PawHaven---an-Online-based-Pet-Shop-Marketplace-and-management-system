<?php

namespace App\Policies;

use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessAccessService;

class BusinessMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        $access = app(BusinessAccessService::class);

        return $access->isInitialSetupAdmin($user)
            || $access->activeMemberships($user)->contains(
                fn (BusinessMembership $membership): bool => in_array(
                    $membership->role,
                    [BusinessAccessService::OWNER, BusinessAccessService::MANAGER],
                    true,
                )
            );
    }

    public function manageMemberships(User $user, Company $company, ?int $branchId = null): bool
    {
        $access = app(BusinessAccessService::class);

        return $access->canManageEmployees($user, $company->id)
            || $access->isInitialSetupAdmin($user);
    }

    public function delete(User $user, BusinessMembership $membership): bool
    {
        return $membership->role !== BusinessAccessService::OWNER
            && app(BusinessAccessService::class)->canManageEmployees($user, $membership->company_id);
    }
}
