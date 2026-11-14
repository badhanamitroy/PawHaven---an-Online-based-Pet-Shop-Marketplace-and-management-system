<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->role !== null && ! in_array($user->role, ['admin', 'staff', 'seller', 'customer', 'user', ''], true)) {
                throw ValidationException::withMessages(['role' => 'Select a supported account role.']);
            }
        });

        static::updated(function (self $user): void {
            if ($user->wasChanged('password')) {
                $user->deviceSessions()
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine whether the user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Determine whether the user is a staff member.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Determine whether the user is a shopkeeper (seller or staff).
     */
    public function isShopkeeper(): bool
    {
        return in_array($this->role, ['seller', 'staff'], true);
    }

    /**
     * Determine whether the user has the default customer role.
     */
    public function isCustomer(): bool
    {
        return in_array($this->role, ['customer', 'user'], true)
            || $this->role === null
            || $this->role === '';
    }

    /**
     * Scope a query to customer accounts, including legacy unassigned roles.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('role', ['customer', 'user'])
                ->orWhereNull('role')
                ->orWhere('role', '');
        });
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    /**
     * @return BelongsToMany<PetListing, $this>
     */
    public function savedPets(): BelongsToMany
    {
        return $this->belongsToMany(PetListing::class, 'saved_pets')->withTimestamps();
    }

    /**
     * @return HasMany<PetListing, $this>
     */
    public function petListings(): HasMany
    {
        return $this->hasMany(PetListing::class, 'seller_id');
    }

    /**
     * @return HasMany<ListingModerationAction, $this>
     */
    public function listingModerationActions(): HasMany
    {
        return $this->hasMany(ListingModerationAction::class, 'admin_user_id');
    }

    /**
     * @return HasMany<UserDeviceSession, $this>
     */
    public function deviceSessions(): HasMany
    {
        return $this->hasMany(UserDeviceSession::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
