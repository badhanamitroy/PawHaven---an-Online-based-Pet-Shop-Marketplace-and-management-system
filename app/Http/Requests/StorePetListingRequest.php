<?php

namespace App\Http\Requests;

use App\Concerns\PetListingValidationRules;
use App\Models\PetListing;
use Illuminate\Foundation\Http\FormRequest;

class StorePetListingRequest extends FormRequest
{
    use PetListingValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', PetListing::class) ?? false;
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
