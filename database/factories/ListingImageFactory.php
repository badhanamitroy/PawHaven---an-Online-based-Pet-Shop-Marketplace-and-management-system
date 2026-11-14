<?php

namespace Database\Factories;

use App\Models\ListingImage;
use App\Models\PetListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingImage>
 */
class ListingImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pet_listing_id' => PetListing::factory(),
            'path' => 'listing-images/'.fake()->uuid().'.jpg',
            'position' => 0,
        ];
    }
}
