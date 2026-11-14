<?php

namespace App\Models;

use Database\Factories\PetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class PetCategory extends Model
{
    /** @use HasFactory<PetCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Breed, $this>
     */
    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }

    /**
     * @return HasMany<PetListing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(PetListing::class);
    }

    /**
     * Alias for listings() relationship.
     *
     * @return HasMany<PetListing, $this>
     */
    public function petListings(): HasMany
    {
        return $this->listings();
    }
}
