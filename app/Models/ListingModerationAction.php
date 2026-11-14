<?php

namespace App\Models;

use App\Enums\ListingModerationActionType;
use Database\Factories\ListingModerationActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ListingModerationActionType $action
 */
#[Fillable(['pet_listing_id', 'admin_user_id', 'action', 'note'])]
class ListingModerationAction extends Model
{
    /** @use HasFactory<ListingModerationActionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['action' => ListingModerationActionType::class];
    }

    /**
     * @return BelongsTo<PetListing, $this>
     */
    public function petListing(): BelongsTo
    {
        return $this->belongsTo(PetListing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
