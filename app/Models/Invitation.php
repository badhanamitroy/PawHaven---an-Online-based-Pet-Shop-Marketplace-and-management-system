<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property string $role
 * @property int $company_id
 * @property int|null $branch_id
 * @property string $token_hash
 * @property string|null $active_email_key
 * @property int|null $invited_by_user_id
 * @property int|null $accepted_user_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read Branch|null $branch
 * @property-read User|null $invitedBy
 * @property-read User|null $acceptedUser
 */
class Invitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'role',
        'company_id',
        'branch_id',
        'token_hash',
        'active_email_key',
        'invited_by_user_id',
        'accepted_user_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isBranchManager(): bool
    {
        return in_array($this->role, ['manager', 'branch_manager'], true);
    }

    public function status(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isAccepted() => 'accepted',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'seller' => 'Seller',
            'manager', 'branch_manager' => 'Branch Manager',
            default => ucfirst($this->role),
        };
    }

    /**
     * Scope to pending active invitations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }
}
