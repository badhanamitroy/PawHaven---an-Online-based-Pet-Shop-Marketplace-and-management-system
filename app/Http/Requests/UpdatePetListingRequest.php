<?php

namespace App\Http\Requests;

use App\Concerns\PetListingValidationRules;
use App\Models\PetListing;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePetListingRequest extends FormRequest
{
    use PetListingValidationRules;

    public function authorize(): bool
    {
        $listing = $this->route('petListing') ?? $this->route('pet_listing');

        return $listing instanceof PetListing
            && ($this->user()?->can('update', $listing) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->integer('pet_category_id');

        return $this->petListingRules($categoryId ?: null);
    }
}
