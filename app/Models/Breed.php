<?php

namespace App\Models;

use Database\Factories\BreedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pet_category_id', 'name', 'slug', 'description', 'care_information'])]
class Breed extends Model
{
    /** @use HasFactory<BreedFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PetCategory, $this>
     */
    public function petCategory(): BelongsTo
    {
        return $this->belongsTo(PetCategory::class);
    }

    /**
     * @return HasMany<PetListing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(PetListing::class);
    }
}
