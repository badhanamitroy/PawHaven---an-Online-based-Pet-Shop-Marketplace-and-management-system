<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int|null $branch_id
 * @property string $role
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Company $company
 * @property-read Branch|null $branch
 */
class BusinessMembership extends Model
{
    protected static function booted(): void
    {
        static::saving(function (BusinessMembership $membership): void {
            if ($membership->role === 'unresolved') {
                if ($membership->is_active || $membership->branch_id !== null) {
                    throw ValidationException::withMessages([
                        'role' => 'Unresolved legacy memberships must remain inactive and unassigned until reviewed.',
                    ]);
                }

                return;
            }

            if (! in_array($membership->role, ['owner', 'manager', 'seller'], true)) {
                throw ValidationException::withMessages(['role' => 'Select a supported business membership role.']);
            }

            if (($membership->role === 'owner') !== ($membership->branch_id === null)) {
                throw ValidationException::withMessages([
                    'branch_id' => $membership->role === 'owner'
                        ? 'The Shop Owner membership must be company-wide.'
                        : 'Branch Manager and Employee Seller memberships must be branch-scoped.',
                ]);
            }

            if (! $membership->is_active) {
                return;
            }

            $company = Company::query()->withoutGlobalScopes()->find($membership->company_id);
            if (! $company?->is_active) {
                throw ValidationException::withMessages(['company_id' => 'An active membership requires an active company.']);
            }
            app(\App\Services\BusinessAccessService::class)->installationCompany((int) $membership->company_id);

            if ($membership->branch_id !== null) {
                $branch = Branch::query()->withoutGlobalScopes()->find($membership->branch_id);
                if (! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $membership->company_id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'The assigned branch must be active and belong to the membership company.',
                    ]);
                }
            }

            $user = User::query()->find($membership->user_id);
            if (! $user?->isStaff()) {
                throw ValidationException::withMessages(['user_id' => 'Only staff accounts can hold business memberships.']);
            }

            $duplicateActive = static::query()
                ->where('user_id', $membership->user_id)
                ->where('is_active', true)
                ->when($membership->exists, fn ($query) => $query->where('id', '!=', $membership->getKey()))
                ->exists();
            if ($duplicateActive) {
                throw ValidationException::withMessages(['user_id' => 'This account already has an active business assignment.']);
            }

            if ($membership->role === 'owner') {
                $anotherOwner = static::query()
                    ->where('company_id', $membership->company_id)
                    ->where('role', 'owner')
                    ->where('is_active', true)
                    ->whereNull('branch_id')
                    ->when($membership->exists, fn ($query) => $query->where('id', '!=', $membership->getKey()))
                    ->exists();

                if ($anotherOwner) {
                    throw ValidationException::withMessages(['role' => 'A primary Shop Owner is already assigned.']);
                }
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'branch_id',
        'role',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'company_id' => 'integer',
            'branch_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
