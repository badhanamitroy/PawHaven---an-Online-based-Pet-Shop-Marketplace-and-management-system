<?php

namespace Database\Seeders;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\Breed;
use App\Models\ListingImage;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('MarketplaceSeeder only creates local demo listings; it was skipped outside local/testing.');

            return;
        }

        $seller = User::where('email', 'seller@example.test')->first();
        if (! $seller) {
            return;
        }

        // 1. Pet Categories
        $dogCat = PetCategory::firstOrCreate(['slug' => 'dogs'], ['name' => 'Dogs']);
        $catCat = PetCategory::firstOrCreate(['slug' => 'cats'], ['name' => 'Cats']);
        $birdCat = PetCategory::firstOrCreate(['slug' => 'birds'], ['name' => 'Birds']);
        $smallPetCat = PetCategory::firstOrCreate(['slug' => 'small-pets'], ['name' => 'Small Pets']);

        // 2. Breeds
        $golden = Breed::firstOrCreate(['slug' => 'golden-retriever'], [
            'pet_category_id' => $dogCat->id,
            'name' => 'Golden Retriever',
            'description' => 'Gentle, intelligent, and affectionate family companions.',
            'care_information' => 'Daily exercise, routine grooming, and balanced protein-rich nutrition.',
        ]);

        $frenchie = Breed::firstOrCreate(['slug' => 'french-bulldog'], [
            'pet_category_id' => $dogCat->id,
            'name' => 'French Bulldog',
            'description' => 'Playful, adaptable companions ideal for apartment living.',
            'care_information' => 'Keep cool in warm weather; clean facial skin folds regularly.',
        ]);

        $persian = Breed::firstOrCreate(['slug' => 'persian-cat'], [
            'pet_category_id' => $catCat->id,
            'name' => 'Persian Cat',
            'description' => 'Calm, quiet, and sweet temperament with a long, luxurious coat.',
            'care_information' => 'Daily coat combing and gentle eye cleaning required.',
        ]);

        // 3. Sample Pet Listings
        $listing1 = PetListing::firstOrCreate(
            ['title' => 'Purebred Golden Retriever Puppy with Health Guarantee'],
            [
                'seller_id' => $seller->id,
                'pet_category_id' => $dogCat->id,
                'breed_id' => $golden->id,
                'description' => 'Gentle, vaccinated, and microchipped 10-week-old Golden Retriever puppy. Raised in a loving home environment with full veterinary health records and ethical breeding certification.',
                'listing_type' => PetListingType::Sale,
                'price_minor' => 125000, // $1,250.00
                'location' => 'Seattle, WA',
                'status' => PetListingStatus::Published,
            ]
        );

        ListingImage::firstOrCreate(
            ['pet_listing_id' => $listing1->id, 'position' => 0],
            ['path' => 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=800&q=80']
        );

        $listing2 = PetListing::firstOrCreate(
            ['title' => 'Adorable French Bulldog Puppy looking for a Caring Family'],
            [
                'seller_id' => $seller->id,
                'pet_category_id' => $dogCat->id,
                'breed_id' => $frenchie->id,
                'description' => 'Compact, well-socialized French Bulldog puppy. Health screened for respiratory and joint vitality. Ready for loving owners.',
                'listing_type' => PetListingType::Sale,
                'price_minor' => 175000, // $1,750.00
                'location' => 'Portland, OR',
                'status' => PetListingStatus::Published,
            ]
        );

        ListingImage::firstOrCreate(
            ['pet_listing_id' => $listing2->id, 'position' => 0],
            ['path' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&w=800&q=80']
        );

        // 4. Adoption Listing
        $listing3 = PetListing::firstOrCreate(
            ['title' => 'Sweet Senior Persian Cat Seeking Gentle Retirement Home'],
            [
                'seller_id' => $seller->id,
                'pet_category_id' => $catCat->id,
                'breed_id' => $persian->id,
                'description' => 'Luna is a quiet 6-year-old Persian cat looking for a tranquil environment. Spayed, fully vaccinated, and very loving.',
                'listing_type' => PetListingType::Adoption,
                'price_minor' => null,
                'location' => 'Bellevue, WA',
                'status' => PetListingStatus::Published,
            ]
        );

        ListingImage::firstOrCreate(
            ['pet_listing_id' => $listing3->id, 'position' => 0],
            ['path' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=800&q=80']
        );

        // 5. Pending Review Listing for Admin Moderation Queue Verification
        $listing4 = PetListing::firstOrCreate(
            ['title' => 'Young British Shorthair Cat Awaiting Clearance'],
            [
                'seller_id' => $seller->id,
                'pet_category_id' => $catCat->id,
                'breed_id' => null,
                'description' => 'Playful young cat pending routine welfare check and document verification by moderation staff.',
                'listing_type' => PetListingType::Sale,
                'price_minor' => 85000,
                'location' => 'Tacoma, WA',
                'status' => PetListingStatus::PendingReview,
            ]
        );

        ListingImage::firstOrCreate(
            ['pet_listing_id' => $listing4->id, 'position' => 0],
            ['path' => 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=800&q=80']
        );
    }
}
