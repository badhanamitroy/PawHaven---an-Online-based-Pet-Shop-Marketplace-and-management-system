<?php

namespace App\Models;

use Database\Factories\ListingImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['pet_listing_id', 'path', 'position'])]
class ListingImage extends Model
{
    /** @use HasFactory<ListingImageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<PetListing, $this>
     */
    public function petListing(): BelongsTo
    {
        return $this->belongsTo(PetListing::class);
    }

    public function url(): string
    {
        $scheme = parse_url($this->path, PHP_URL_SCHEME);

        if (in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return $this->path;
        }

        return Storage::disk('public')->url($this->path);
    }
}
