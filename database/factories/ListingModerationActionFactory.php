<?php

namespace Database\Factories;

use App\Enums\ListingModerationActionType;
use App\Models\ListingModerationAction;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingModerationAction>
 */
class ListingModerationActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pet_listing_id' => PetListing::factory(),
            'admin_user_id' => User::factory()->state(['role' => 'admin']),
            'action' => ListingModerationActionType::Approved,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
