<?php

namespace Database\Factories;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetListing>
 */
class PetListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->state(['role' => 'seller']),
            'pet_category_id' => PetCategory::factory(),
            'breed_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'listing_type' => PetListingType::Sale,
            'price_minor' => fake()->numberBetween(100, 100000),
            'location' => fake()->city(),
            'status' => PetListingStatus::Draft,
        ];
    }

    public function adoption(): static
    {
        return $this->state(fn (): array => [
            'listing_type' => PetListingType::Adoption,
            'price_minor' => null,
        ]);
    }
}
