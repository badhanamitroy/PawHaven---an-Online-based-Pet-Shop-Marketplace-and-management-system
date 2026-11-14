<?php

namespace App\Concerns;

use App\Enums\PetListingType;
use Illuminate\Validation\Rule;

trait PetListingValidationRules
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function petListingRules(?int $petCategoryId): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'pet_category_id' => ['required', 'integer', Rule::exists('pet_categories', 'id')],
            'breed_id' => [
                'nullable',
                'integer',
                Rule::exists('breeds', 'id')->where('pet_category_id', $petCategoryId),
            ],
            'listing_type' => ['required', Rule::enum(PetListingType::class)],
            'price_minor' => ['required_if:listing_type,sale', 'nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
