<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\BusinessAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Adds fail-closed company/branch visibility and safe default tenant assignment.
 *
 * @phpstan-require-extends Model
 */
trait HasBusinessScope
{
    protected static function bootHasBusinessScope(): void
    {
        static::addGlobalScope('business_access', function (Builder $query): void {
            $user = Auth::user();

            if ($user instanceof User && ($user->isStaff() || $user->isAdmin())) {
                app(BusinessAccessService::class)->applyScope($query, $user);
            }
        });

        static::creating(function (Model $model): void {
            if (! Schema::hasTable('companies') || ! Schema::hasColumn($model->getTable(), 'company_id')) {
                return;
            }

            $user = Auth::user();
            $actor = $user instanceof User ? $user : null;
            if ($model instanceof BusinessCompanyAssignmentAware
                && ! $model->shouldAssignDefaultBusinessCompany($actor)) {
                return;
            }

            $branchColumn = $model instanceof BusinessBranchScoped
                ? $model->businessBranchColumn()
                : null;
            $assignment = app(BusinessAccessService::class)->creationAssignment(
                $actor,
                $branchColumn !== null && ! ($model instanceof Branch),
                $model->getAttribute('company_id') === null ? null : (int) $model->getAttribute('company_id'),
                $branchColumn === null
                    || $model->getAttribute($branchColumn) === null
                    ? null
                    : (int) $model->getAttribute($branchColumn),
                $model,
            );

            if ($model->getAttribute('company_id') === null && $assignment['company_id'] !== null) {
                $model->setAttribute('company_id', $assignment['company_id']);
            }

            if ($branchColumn !== null
                && ! ($model instanceof Branch)
                && $model->getAttribute($branchColumn) === null
                && $assignment['branch_id'] !== null) {
                $model->setAttribute($branchColumn, $assignment['branch_id']);
            }
        });
    }
}
