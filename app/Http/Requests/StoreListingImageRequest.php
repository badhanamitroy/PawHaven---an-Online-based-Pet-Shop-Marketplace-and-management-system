<?php

namespace App\Http\Requests;

use App\Models\PetListing;
use Illuminate\Foundation\Http\FormRequest;

class StoreListingImageRequest extends FormRequest
{
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
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
