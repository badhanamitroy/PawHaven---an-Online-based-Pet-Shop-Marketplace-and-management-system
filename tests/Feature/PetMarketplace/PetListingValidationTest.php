<?php

namespace Tests\Feature\PetMarketplace;

use App\Concerns\PetListingValidationRules;
use App\Enums\PetListingType;
use App\Http\Requests\StoreListingImageRequest;
use App\Models\Breed;
use App\Models\PetCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PetListingValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_listing_requires_a_non_negative_price(): void
    {
        $category = PetCategory::factory()->create();

        $this->assertValidationFails($this->listingData($category->id, ['price_minor' => null]), 'price_minor');
        $this->assertValidationFails($this->listingData($category->id, ['price_minor' => -1]), 'price_minor');
        $this->assertValidationPasses($this->listingData($category->id, ['price_minor' => 0]));
    }

    public function test_non_sale_listings_can_have_no_price(): void
    {
        $category = PetCategory::factory()->create();

        foreach ([PetListingType::Adoption, PetListingType::Rescue, PetListingType::Rehoming, PetListingType::LostFound] as $type) {
            $this->assertValidationPasses($this->listingData($category->id, [
                'listing_type' => $type->value,
                'price_minor' => null,
            ]));
        }
    }

    public function test_breed_must_belong_to_the_selected_category(): void
    {
        $category = PetCategory::factory()->create();
        $otherCategory = PetCategory::factory()->create();
        $breed = Breed::factory()->for($otherCategory, 'petCategory')->create();

        $this->assertValidationFails(
            $this->listingData($category->id, ['breed_id' => $breed->id]),
            'breed_id',
        );
    }

    public function test_breed_belonging_to_selected_category_passes_validation(): void
    {
        $category = PetCategory::factory()->create();
        $breed = Breed::factory()->for($category, 'petCategory')->create();

        $this->assertValidationPasses(
            $this->listingData($category->id, ['breed_id' => $breed->id]),
        );
    }

    public function test_non_sale_listing_with_negative_price_fails_validation(): void
    {
        $category = PetCategory::factory()->create();

        $this->assertValidationFails(
            $this->listingData($category->id, [
                'listing_type' => PetListingType::Adoption->value,
                'price_minor' => -50,
            ]),
            'price_minor',
        );
    }

    public function test_listing_type_must_be_supported(): void
    {
        $category = PetCategory::factory()->create();

        $this->assertValidationFails(
            $this->listingData($category->id, ['listing_type' => 'wildlife_trade']),
            'listing_type',
        );
    }

    public function test_listing_image_request_validates_image_type_size_and_position(): void
    {
        $rules = (new StoreListingImageRequest)->rules();

        $validImage = UploadedFile::fake()->create('pet.jpg', 100, 'image/jpeg');
        $this->assertTrue(Validator::make([
            'image' => $validImage,
            'position' => 0,
        ], $rules)->passes());

        $invalidImage = UploadedFile::fake()->create('pet.svg', 100, 'image/svg+xml');
        $this->assertTrue(Validator::make(['image' => $invalidImage], $rules)->fails());
        $this->assertTrue(Validator::make([
            'image' => UploadedFile::fake()->create('pet.jpg', 100, 'image/jpeg'),
            'position' => 65536,
        ], $rules)->fails());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listingData(int $categoryId, array $overrides = []): array
    {
        return array_replace([
            'title' => 'Friendly pet',
            'description' => 'Looking for a suitable home.',
            'pet_category_id' => $categoryId,
            'breed_id' => null,
            'listing_type' => PetListingType::Sale->value,
            'price_minor' => 1000,
            'location' => 'Dhaka',
        ], $overrides);
    }

    private function assertValidationFails(array $data, string $field): void
    {
        $validator = Validator::make($data, $this->rulesFor((int) $data['pet_category_id']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    private function assertValidationPasses(array $data): void
    {
        $this->assertTrue(Validator::make(
            $data,
            $this->rulesFor((int) $data['pet_category_id']),
        )->passes());
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function rulesFor(int $categoryId): array
    {
        return (new class
        {
            use PetListingValidationRules;

            public function rulesForCategory(int $categoryId): array
            {
                return $this->petListingRules($categoryId);
            }
        })->rulesForCategory($categoryId);
    }
}
