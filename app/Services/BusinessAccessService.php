<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\AdminAuditLog;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BusinessAccessService
{
    public const OWNER = 'owner';

    public const MANAGER = 'manager';

    public const EMPLOYEE_SELLER = 'seller';

    /**
     * Restrict a business model query to active company and branch memberships.
     * Platform Admin intentionally has no implicit business membership.
     *
     * @template TModel of Model
     * @param Builder<TModel> $query
     */
    public function applyScope(Builder $query, User $user): void
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $companyColumn = $model instanceof Company ? 'id' : 'company_id';
        $branchColumn = $model instanceof Branch
            ? 'id'
            : ($model instanceof BusinessBranchScoped ? $model->businessBranchColumn() : null);

        if (! $user->isStaff()
            || ! Schema::hasTable('business_memberships')
            || ! Schema::hasColumn($table, $companyColumn)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $memberships = $this->activeMemberships($user);
        if ($model instanceof Order) {
            $memberships = $memberships->filter(fn (BusinessMembership $membership): bool =>
                in_array($membership->role, [self::OWNER, self::MANAGER], true)
            );
        }
        if ($memberships->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $byCompany = $memberships->groupBy('company_id');
        $query->where(function (Builder $visible) use ($byCompany, $companyColumn, $branchColumn, $table): void {
            foreach ($byCompany as $companyId => $companyMemberships) {
                $companyWide = $companyMemberships->contains(
                    fn (BusinessMembership $membership): bool => $membership->role === self::OWNER
                        && $membership->branch_id === null
                );
                $branchIds = $companyMemberships->whereNotNull('branch_id')
                    ->pluck('branch_id')->unique()->values()->all();

                $visible->orWhere(function (Builder $companyQuery) use (
                    $companyWide,
                    $branchColumn,
                    $branchIds,
                    $companyColumn,
                    $companyId,
                    $table
                ): void {
                    $companyQuery->where($table.'.'.$companyColumn, (int) $companyId);

                    if ($branchColumn !== null && ! $companyWide) {
                        if ($branchIds === []) {
                            $companyQuery->whereRaw('1 = 0');
                        } else {
                            $companyQuery->whereIn($table.'.'.$branchColumn, $branchIds);
                        }
                    }
                });
            }
        });
    }

    /** @return Collection<int, BusinessMembership> */
    public function activeMemberships(User $user): Collection
    {
        if (! $user->isStaff() || ! Schema::hasTable('business_memberships')) {
            return collect();
        }

        if (! Schema::hasTable('companies')
            || Company::query()->withoutGlobalScopes()->count() !== 1) {
            return collect();
        }

        $companyId = Company::query()->withoutGlobalScopes()->value('id');
        $activeOwnerCount = BusinessMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('role', self::OWNER)
            ->whereNull('branch_id')
            ->count();
        if ($activeOwnerCount !== 1) {
            return collect();
        }

        $memberships = BusinessMembership::query()
            ->where('user_id', $user->getKey())
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $owner): Builder => $owner
                    ->where('role', self::OWNER)->whereNull('branch_id'))
                    ->orWhere(fn (Builder $employee): Builder => $employee
                        ->whereIn('role', [self::MANAGER, self::EMPLOYEE_SELLER])
                        ->whereNotNull('branch_id'));
            })
            ->whereHas('company', function (Builder $query): void {
                $query->withoutGlobalScopes()->where('is_active', true);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('branch_id')->orWhereHas('branch', function (Builder $branch): void {
                    $branch->withoutGlobalScopes()
                        ->whereColumn('branches.company_id', 'business_memberships.company_id')
                        ->where('branches.is_active', true);
                });
            })
            ->get();

        // An employee must have exactly one active assignment. Multiple records can
        // indicate legacy or concurrent corruption, so authorization fails closed.
        return $memberships->count() === 1 ? $memberships : collect();
    }

    public function hasBusinessAccess(User $user): bool
    {
        return $user->isStaff() && $this->activeMemberships($user)->isNotEmpty();
    }

    public function canAccessCompany(User $user, int $companyId): bool
    {
        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
        );
    }

    public function canAccessRecord(User $user, ?int $companyId, ?int $branchId): bool
    {
        if (! $user->isStaff() || $companyId === null) {
            return false;
        }

        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
                && (($membership->role === self::OWNER && $membership->branch_id === null)
                    || ($membership->branch_id !== null && $membership->branch_id === $branchId))
        );
    }

    public function canManageCompany(User $user, int $companyId, ?int $branchId = null): bool
    {
        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
                && $membership->role === self::OWNER
                && $membership->branch_id === null
        );
    }

    public function canManageEmployees(User $user, int $companyId): bool
    {
        return $this->canManageCompany($user, $companyId);
    }

    public function canViewEmployeeDirectory(User $user, int $companyId, ?int $branchId = null): bool
    {
        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
                && (($membership->role === self::OWNER && $membership->branch_id === null)
                    || ($membership->role === self::MANAGER
                        && $branchId !== null
                        && $membership->branch_id === $branchId))
        );
    }

    public function canViewProducts(User $user, ?int $companyId, ?int $branchId): bool
    {
        return $this->canAccessRecord($user, $companyId, $branchId);
    }

    public function canManageProducts(User $user, ?int $companyId): bool
    {
        return $companyId !== null && $this->canManageCompany($user, $companyId);
    }

    public function canViewInventory(User $user, ?int $companyId, ?int $branchId): bool
    {
        if (! $user->isStaff() || $companyId === null) {
            return false;
        }

        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
                && (($membership->role === self::OWNER && $membership->branch_id === null)
                    || ($membership->role === self::MANAGER && $membership->branch_id === $branchId))
        );
    }

    public function canManageInventory(User $user, ?int $companyId): bool
    {
        return $companyId !== null && $this->canManageCompany($user, $companyId);
    }

    public function canViewOrders(User $user, ?int $companyId, ?int $branchId): bool
    {
        if (! $user->isStaff() || $companyId === null) {
            return false;
        }

        return $this->activeMemberships($user)->contains(
            fn (BusinessMembership $membership): bool => $membership->company_id === $companyId
                && (($membership->role === self::OWNER && $membership->branch_id === null)
                    || ($membership->role === self::MANAGER && $branchId !== null && $membership->branch_id === $branchId))
        );
    }

    public function canManageOrders(User $user, ?int $companyId, ?int $branchId): bool
    {
        return $this->canViewOrders($user, $companyId, $branchId);
    }

    /** Resolve the installation's only company, rejecting absent or ambiguous state. */
    public function installationCompany(?int $requestedId = null, bool $requireActive = true): Company
    {
        if (! Schema::hasTable('companies')) {
            throw new AuthorizationException('The installation company has not been configured.');
        }

        $companies = Company::query()->withoutGlobalScopes()->orderBy('id')->get();
        if ($companies->count() !== 1) {
            throw new AuthorizationException($companies->isEmpty()
                ? 'The installation company has not been configured.'
                : 'This installation contains multiple companies and requires administrator review.');
        }

        /** @var Company $company */
        $company = $companies->first();
        if ($requestedId !== null && (int) $company->id !== $requestedId) {
            throw new AuthorizationException('The requested company is not the company assigned to this installation.');
        }

        if ($requireActive && ! $company->is_active) {
            throw new AuthorizationException('The installation company is inactive.');
        }

        return $company;
    }

    public function isInitialSetupAdmin(User $user): bool
    {
        if (! $user->isAdmin() || ! Schema::hasTable('companies')) {
            return false;
        }

        $companies = Company::query()->withoutGlobalScopes()->count();
        if ($companies === 0) {
            return true;
        }
        if ($companies !== 1 || ! Schema::hasTable('business_memberships')) {
            return false;
        }

        return ! BusinessMembership::query()
            ->where('company_id', Company::query()->withoutGlobalScopes()->value('id'))
            ->where('is_active', true)
            ->where('role', self::OWNER)
            ->whereNull('branch_id')
            ->exists();
    }

    /** @return array{company_id: int|null, branch_id: int|null} */
    public function creationAssignment(
        ?User $user,
        bool $hasBranch,
        ?int $requestedCompanyId = null,
        ?int $requestedBranchId = null,
        ?Model $model = null,
    ): array {
        if ($user?->isStaff()) {
            $memberships = $this->activeMemberships($user);
            if ($memberships->isEmpty()) {
                throw new AuthorizationException('An active business membership is required.');
            }

            $companyIds = $memberships->pluck('company_id')->unique()->values();
            if ($companyIds->count() !== 1) {
                throw new AuthorizationException('This installation has ambiguous company assignments.');
            }
            $company = $this->installationCompany($requestedCompanyId);
            if ((int) $company->id !== (int) $companyIds->first()) {
                throw new AuthorizationException('You are not assigned to the installation company.');
            }

            $isOwner = $memberships->contains(fn (BusinessMembership $membership): bool =>
                $membership->role === self::OWNER && $membership->branch_id === null
            );
            $hasOperationalAccess = $isOwner || $memberships->contains(
                fn (BusinessMembership $membership): bool => $membership->role === self::MANAGER
            );
            $isEmployeeSeller = $memberships->contains(
                fn (BusinessMembership $membership): bool => $membership->role === self::EMPLOYEE_SELLER
            );
            if (($model instanceof Product || $model instanceof ProductCategory || $model instanceof Branch) && ! $isOwner) {
                throw new AuthorizationException('Only the Shop Owner can create or change company catalogue and branch records.');
            }
            if (($model instanceof AdminAuditLog && ! $hasOperationalAccess)
                || ($model instanceof Order && ! $hasOperationalAccess && ! $isEmployeeSeller)) {
                throw new AuthorizationException('This employee position cannot create business order or audit records.');
            }

            if ($hasBranch && $requestedBranchId !== null) {
                $branch = Branch::query()->withoutGlobalScopes()
                    ->whereKey($requestedBranchId)->where('company_id', $company->id)
                    ->where('is_active', true)->first();
                if (! $branch) {
                    throw new AuthorizationException('Select an active branch in the installation company.');
                }
            }

            if ($isOwner) {
                return [
                    'company_id' => (int) $company->id,
                    'branch_id' => $hasBranch ? $requestedBranchId : null,
                ];
            }

            if ($memberships->contains(fn (BusinessMembership $membership): bool => $membership->role === self::MANAGER)) {
                $assignedBranches = $memberships->where('role', self::MANAGER)->pluck('branch_id')->unique()->values();
                if ($assignedBranches->count() !== 1) {
                    throw new AuthorizationException('A single assigned branch is required for this operation.');
                }
                $assignedBranchId = (int) $assignedBranches->first();
                if ($requestedBranchId !== null && $requestedBranchId !== $assignedBranchId) {
                    throw new AuthorizationException('You are not assigned to the selected branch.');
                }

                return [
                    'company_id' => (int) $company->id,
                    'branch_id' => $hasBranch ? $assignedBranchId : null,
                ];
            }

            if ($model instanceof Order && $isEmployeeSeller) {
                $sellerMembership = $memberships->firstWhere('role', self::EMPLOYEE_SELLER);
                $assignedBranchId = (int) $sellerMembership->branch_id;
                if ($requestedBranchId !== null && $requestedBranchId !== $assignedBranchId) {
                    throw new AuthorizationException('Employee Sellers can only attribute orders to their assigned branch.');
                }

                return [
                    'company_id' => (int) $company->id,
                    'branch_id' => $assignedBranchId,
                ];
            }

            throw new AuthorizationException('This business membership cannot create operational records.');
        }

        if ($user?->isAdmin()) {
            throw new AuthorizationException('Platform administrators do not have implicit business access.');
        }

        if ($user !== null
            && (! $user->isCustomer() || ($model !== null && ! ($model instanceof Order)))) {
            throw new AuthorizationException('This account cannot create business operational records.');
        }

        $company = $this->installationCompany($requestedCompanyId);
        if ($requestedBranchId !== null) {
            throw new AuthorizationException('A branch cannot be selected through this operation.');
        }

        return ['company_id' => (int) $company->id, 'branch_id' => null];
    }
}
