<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessMembershipService
{
    public function assign(User $actor, User $target, Company $company, ?int $branchId, string $role): BusinessMembership
    {
        $access = app(BusinessAccessService::class);
        $initialOwnerAssignment = $role === BusinessAccessService::OWNER
            && $branchId === null
            && $access->isInitialSetupAdmin($actor);

        if (! $initialOwnerAssignment && ! $access->canManageEmployees($actor, (int) $company->id)) {
            throw new AuthorizationException('Only the Shop Owner can manage employee assignments.');
        }

        if ($target->id === $actor->id) {
            throw new AuthorizationException('You cannot change your own business membership.');
        }

        return $this->assignForCompany($target, $company, $branchId, $role, $initialOwnerAssignment);
    }

    public function assignForInvitation(User $target, Invitation $invitation): BusinessMembership
    {
        if (! $invitation->exists) {
            throw new AuthorizationException('A persisted invitation is required for employee membership activation.');
        }

        return DB::transaction(function () use ($target, $invitation): BusinessMembership {
            $company = Company::query()->withoutGlobalScopes()
                ->whereKey($invitation->company_id)->lockForUpdate()->firstOrFail();
            $current = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->first();

            if (! $current
                || ! $current->isAccepted()
                || ! $current->isBranchManager()
                || $current->branch_id === null
                || (int) $current->accepted_user_id !== (int) $target->id
                || strtolower(trim($current->email)) !== strtolower(trim($target->email))) {
                throw new AuthorizationException('This invitation does not authorize the requested employee membership.');
            }

            $membershipAlreadyExists = BusinessMembership::query()
                ->where('user_id', $target->id)
                ->where('company_id', $company->id)
                ->exists();
            if ($membershipAlreadyExists) {
                throw new AuthorizationException('An invitation cannot be reused to restore or replace a membership.');
            }

            return $this->assignForCompany(
                $target,
                $company,
                (int) $current->branch_id,
                BusinessAccessService::MANAGER,
                false,
            );
        });
    }

    public function deactivate(User $actor, BusinessMembership $membership): void
    {
        if ($membership->role === BusinessAccessService::OWNER) {
            throw new AuthorizationException('The primary Shop Owner cannot be removed without an approved ownership transfer.');
        }

        if (! app(BusinessAccessService::class)->canManageEmployees($actor, (int) $membership->company_id)) {
            throw new AuthorizationException('Only the Shop Owner can manage employee assignments.');
        }

        DB::transaction(function () use ($membership): void {
            $company = Company::query()->withoutGlobalScopes()
                ->whereKey($membership->company_id)->lockForUpdate()->firstOrFail();
            $locked = BusinessMembership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            if ($locked->role === BusinessAccessService::OWNER) {
                throw new AuthorizationException('The primary Shop Owner cannot be removed without an approved ownership transfer.');
            }

            $locked->is_active = false;
            $locked->save();
        });
    }

    private function assignForCompany(
        User $target,
        Company $company,
        ?int $branchId,
        string $role,
        bool $initialOwnerAssignment,
    ): BusinessMembership {
        if (! $target->isStaff() && ! $initialOwnerAssignment) {
            throw ValidationException::withMessages([
                'user_id' => 'Only a staff account can receive an employee membership.',
            ]);
        }

        if (! in_array($role, [
            BusinessAccessService::OWNER,
            BusinessAccessService::MANAGER,
            BusinessAccessService::EMPLOYEE_SELLER,
        ], true)) {
            throw ValidationException::withMessages(['role' => 'Select a supported employee position.']);
        }

        if (($role === BusinessAccessService::OWNER) !== ($branchId === null)) {
            throw ValidationException::withMessages([
                'branch_id' => $role === BusinessAccessService::OWNER
                    ? 'The Shop Owner must have company-wide access.'
                    : 'Branch Managers and Employee Sellers must be assigned to an active branch.',
            ]);
        }

        if ($role === BusinessAccessService::OWNER && ! $initialOwnerAssignment) {
            throw new AuthorizationException('Shop Owner assignment is available only during initial business setup.');
        }

        return DB::transaction(function () use ($company, $branchId, $role, $target, $initialOwnerAssignment): BusinessMembership {
            $lockedCompany = Company::query()->withoutGlobalScopes()
                ->whereKey($company->id)->lockForUpdate()->firstOrFail();
            app(BusinessAccessService::class)->installationCompany((int) $lockedCompany->id);

            if (! $lockedCompany->is_active) {
                throw ValidationException::withMessages(['company_id' => 'The installation company is inactive.']);
            }

            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $lockedTarget->isStaff()) {
                if (! $initialOwnerAssignment
                    || ! $lockedTarget->isCustomer()
                    || $lockedTarget->email_verified_at === null
                    || $lockedTarget->orders()->exists()
                    || $lockedTarget->savedPets()->exists()
                    || $lockedTarget->petListings()->exists()
                    || $lockedTarget->sellerOrders()->exists()) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Initial Owner setup requires a verified, unassigned Customer account with no marketplace or business records.',
                    ]);
                }

                $lockedTarget->role = 'staff';
                $lockedTarget->save();
            }

            if ($branchId !== null) {
                $branch = Branch::query()->withoutGlobalScopes()
                    ->whereKey($branchId)->lockForUpdate()->first();
                if (! $branch
                    || ! $branch->is_active
                    || (int) $branch->company_id !== (int) $lockedCompany->id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Choose an active branch belonging to the installation company.',
                    ]);
                }
            }

            $memberships = BusinessMembership::query()
                ->where('company_id', $lockedCompany->id)
                ->where('user_id', $target->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $activeMemberships = $memberships->where('is_active', true);
            if ($activeMemberships->count() > 1) {
                throw ValidationException::withMessages([
                    'user_id' => 'This account has conflicting active memberships and requires administrator review.',
                ]);
            }

            $active = $activeMemberships->first();
            if ($active !== null && ! $initialOwnerAssignment
                && ($active->branch_id !== $branchId || $active->role === BusinessAccessService::OWNER && $role !== BusinessAccessService::OWNER)) {
                throw ValidationException::withMessages([
                    'user_id' => 'Deactivate the existing assignment before assigning this employee to a different position or branch.',
                ]);
            }

            $otherActive = BusinessMembership::query()
                ->where('user_id', $target->id)
                ->where('is_active', true)
                ->where('company_id', '!=', $lockedCompany->id)
                ->exists();
            if ($otherActive) {
                throw ValidationException::withMessages([
                    'user_id' => 'This employee already has an active business assignment.',
                ]);
            }

            if ($role === BusinessAccessService::OWNER) {
                if (! $initialOwnerAssignment) {
                    throw new AuthorizationException('Only the initial Shop Owner can be assigned in setup.');
                }

                $otherOwner = BusinessMembership::query()
                    ->where('company_id', $lockedCompany->id)
                    ->where('role', BusinessAccessService::OWNER)
                    ->where('is_active', true)
                    ->whereNull('branch_id')
                    ->where('user_id', '!=', $target->id)
                    ->exists();
                if ($otherOwner) {
                    throw ValidationException::withMessages([
                        'role' => 'A primary Shop Owner is already assigned to this company.',
                    ]);
                }

                $targetAlreadyOwner = $memberships->contains(fn (BusinessMembership $membership): bool =>
                    $membership->is_active
                    && $membership->role === BusinessAccessService::OWNER
                    && $membership->branch_id === null
                );
                if ($targetAlreadyOwner) {
                    return $memberships->first(fn (BusinessMembership $membership): bool =>
                        $membership->is_active && $membership->role === BusinessAccessService::OWNER
                    );
                }
            }

            $historical = $memberships->first(fn (BusinessMembership $membership): bool => ! $membership->is_active);
            $membership = $active ?? $historical ?? new BusinessMembership;
            $membership->forceFill([
                'user_id' => $target->id,
                'company_id' => $lockedCompany->id,
                'branch_id' => $branchId,
                'role' => $role,
                'is_active' => true,
            ])->save();

            return $membership;
        });
    }
}
