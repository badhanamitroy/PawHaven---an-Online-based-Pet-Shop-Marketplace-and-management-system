<?php

namespace App\Models;

use App\Enums\ListingModerationActionType;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use Database\Factories\PetListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @property PetListingType $listing_type
 * @property int|null $price_minor
 * @property PetListingStatus $status
 */
#[Fillable(['seller_id', 'pet_category_id', 'breed_id', 'title', 'description', 'listing_type', 'price_minor', 'location', 'status'])]
class PetListing extends Model
{
    /** @use HasFactory<PetListingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'listing_type' => PetListingType::class,
            'status' => PetListingStatus::class,
            'price_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<PetCategory, $this>
     */
    public function petCategory(): BelongsTo
    {
        return $this->belongsTo(PetCategory::class);
    }

    /**
     * @return BelongsTo<Breed, $this>
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /**
     * @return HasMany<ListingImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('position');
    }

    /**
     * @return HasMany<ListingModerationAction, $this>
     */
    public function moderationActions(): HasMany
    {
        return $this->hasMany(ListingModerationAction::class)->latest();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pet_listing_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_pets')->withTimestamps();
    }

    public function submitForReview(User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $this);

        if (! in_array($this->status, [PetListingStatus::Draft, PetListingStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => __('Only draft or rejected listings can be submitted for review.'),
            ]);
        }

        $this->status = PetListingStatus::PendingReview;
        $this->save();
    }

    public function pause(User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $this);

        if ($this->status !== PetListingStatus::Published) {
            throw ValidationException::withMessages([
                'status' => __('Only published listings can be paused.'),
            ]);
        }

        $this->status = PetListingStatus::Paused;
        $this->save();
    }

    public function markCompleted(User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $this);

        if (! in_array($this->status, [PetListingStatus::Published, PetListingStatus::Paused], true)) {
            throw ValidationException::withMessages([
                'status' => __('Only published or paused listings can be marked as completed.'),
            ]);
        }

        $this->status = PetListingStatus::Completed;
        $this->save();
    }

    public function moderate(
        User $admin,
        ListingModerationActionType $action,
        ?string $note = null,
    ): ListingModerationAction {
        Gate::forUser($admin)->authorize('moderate', $this);

        $nextStatus = match ($action) {
            ListingModerationActionType::Approved => $this->moderationStatus(PetListingStatus::Published),
            ListingModerationActionType::Rejected => $this->moderationStatus(PetListingStatus::Rejected),
            ListingModerationActionType::Removed => $this->moderationStatus(PetListingStatus::Removed),
        };

        return DB::transaction(function () use ($admin, $action, $note, $nextStatus): ListingModerationAction {
            $this->status = $nextStatus;
            $this->save();

            return $this->moderationActions()->create([
                'admin_user_id' => $admin->id,
                'action' => $action,
                'note' => $note,
            ]);
        });
    }

    private function moderationStatus(PetListingStatus $nextStatus): PetListingStatus
    {
        $allowed = match ($nextStatus) {
            PetListingStatus::Published, PetListingStatus::Rejected => [PetListingStatus::PendingReview],
            PetListingStatus::Removed => [
                PetListingStatus::Draft,
                PetListingStatus::PendingReview,
                PetListingStatus::Published,
                PetListingStatus::Rejected,
                PetListingStatus::Paused,
            ],
            default => [],
        };

        if (! in_array($this->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('This moderation action is not available for the listing status.'),
            ]);
        }

        return $nextStatus;
    }
}
