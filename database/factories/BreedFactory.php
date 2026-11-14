<?php

namespace Database\Factories;

use App\Models\Breed;
use App\Models\PetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Breed>
 */
class BreedFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->firstName();

        return [
            'pet_category_id' => PetCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'care_information' => fake()->optional()->paragraph(),
        ];
    }
}
