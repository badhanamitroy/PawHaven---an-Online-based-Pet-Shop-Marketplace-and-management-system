<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ShopSeeder only creates local demo catalog data; it was skipped outside local/testing.');

            return;
        }

        // Demo catalog data is for local development and tests only.
        $categories = [
            ['name' => 'Food & Treats', 'slug' => 'food-treats', 'description' => 'Organic kibble, raw diets, hypoallergenic recipes, and dental chews.', 'order' => 1],
            ['name' => 'Toys & Enrichment', 'slug' => 'toys-enrichment', 'description' => 'Puzzle feeders, durable rubber toys, interactive wands, and scratchers.', 'order' => 2],
            ['name' => 'Collars & Leashes', 'slug' => 'collars-leashes', 'description' => 'Ergonomic no-pull harnesses, reflective leashes, and custom ID tags.', 'order' => 3],
            ['name' => 'Grooming & Care', 'slug' => 'grooming-care', 'description' => 'Natural oatmeal shampoos, deshedding brushes, ear cleaners, and balms.', 'order' => 4],
            ['name' => 'Beds & Carriers', 'slug' => 'beds-carriers', 'description' => 'Orthopedic memory foam mattresses, airline-ready crates, and cozy blankets.', 'order' => 5],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['slug']] = ProductCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }

        $initialProducts = [
            [
                'category_slug' => 'food-treats',
                'name' => 'PawHaven Heritage All-Natural Salmon & Rice Dry Food (12kg)',
                'sku' => 'PH-FOOD-SALMON-12KG',
                'description' => 'Hypoallergenic blend formulated with omega-rich wild salmon and brown rice for coat vitality and gentle digestion.',
                'price_minor' => 6499,
                'sale_price_minor' => 5999,
                'stock_quantity' => 28,
                'low_stock_threshold' => 5,
                'is_published' => true,
                'is_featured' => true,
                'image_path' => 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&w=600&q=80',
                'ingredients' => 'Wild Pacific Salmon, Whole Grain Brown Rice, Pea Protein, Flaxseed, Dried Kelp, Vitamins and Minerals.',
                'storage_instructions' => 'Store in a cool, dry place. Seal tightly after opening.',
            ],
            [
                'category_slug' => 'collars-leashes',
                'name' => 'PawHaven All-Weather Ergonomic No-Pull Harness',
                'sku' => 'PH-HARNESS-ALLWEATHER-M',
                'description' => 'Dual alloy leash attachment rings, breathable air-mesh chest padding, and 3M reflective safety stitching for walks.',
                'price_minor' => 3450,
                'sale_price_minor' => null,
                'stock_quantity' => 42,
                'low_stock_threshold' => 8,
                'is_published' => true,
                'is_featured' => true,
                'image_path' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&w=600&q=80',
                'ingredients' => null,
                'storage_instructions' => 'Hand wash with mild detergent. Air dry away from direct heat.',
            ],
            [
                'category_slug' => 'beds-carriers',
                'name' => 'PawHaven Orthopedic Memory Foam Calming Bed',
                'sku' => 'PH-BED-ORTHO-LG',
                'description' => 'High-density orthopedic joint relief foam with waterproof internal casing and removable plush washable cover.',
                'price_minor' => 7900,
                'sale_price_minor' => null,
                'stock_quantity' => 15,
                'low_stock_threshold' => 4,
                'is_published' => true,
                'is_featured' => true,
                'image_path' => 'https://images.unsplash.com/photo-1541781774459-bb2af2f05b55?auto=format&fit=crop&w=600&q=80',
                'ingredients' => null,
                'storage_instructions' => 'Cover is machine washable on cold cycle.',
            ],
            [
                'category_slug' => 'food-treats',
                'name' => 'PawHaven Natural Sea Kelp Dental Chew Sticks (30-Pack)',
                'sku' => 'PH-TREAT-DENTAL-KELP-30',
                'description' => 'Organic sea kelp and mint formulation combating plaque buildup and keeping breath clean naturally.',
                'price_minor' => 1899,
                'sale_price_minor' => null,
                'stock_quantity' => 60,
                'low_stock_threshold' => 10,
                'is_published' => true,
                'is_featured' => true,
                'image_path' => 'https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&w=600&q=80',
                'ingredients' => 'Potato Starch, Vegetable Glycerin, Dried Organic Sea Kelp, Spearmint Extract, Parsley.',
                'storage_instructions' => 'Keep in resealable pouch in a dry location.',
            ],
        ];

        foreach ($initialProducts as $item) {
            $catSlug = $item['category_slug'];
            unset($item['category_slug']);
            $item['product_category_id'] = $categoryMap[$catSlug]->id;
            $item['slug'] = Str::slug($item['name']);

            Product::firstOrCreate(
                ['sku' => $item['sku']],
                $item
            );
        }

    }
}
