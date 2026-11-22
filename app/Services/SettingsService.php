<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    /**
     * In-memory cache for the current request lifecycle.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $memoryCache = null;

    /**
     * Default baseline settings for a fresh installation.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Brand & Identity
            'brand.name' => 'PawHaven',
            'brand.tagline' => 'Find Pets. Give Hope. Shop with Love.',
            'brand.primary_color' => '#245C45',
            'brand.secondary_color' => '#173F32',
            'brand.accent_color' => '#F28C75',
            'brand.hero_banner_path' => '/Media/Images/Hero-Banner.png',
            'brand.logo_light_path' => '/Media/Logos/PawHeaven-Green-logo.png',
            'brand.logo_dark_path' => '/Media/Logos/PawHeaven-White-logo.png',
            'brand.logo_navy_path' => '/Media/Logos/PawHeaven-NavyBlue-logo.png',
            'brand.favicon_path' => '/Media/Logos/PawHeaven-icon.png',
            'brand.custom_paw_cursor' => false,
            'brand.contact_email' => '',
            'brand.phone' => '',
            'brand.address' => '',
            'brand.social_facebook' => '',
            'brand.social_instagram' => '',
            'brand.social_x' => '',
            'brand.meta_description' => 'PawHaven is a premium pet marketplace, adoption and rescue community, breed encyclopedia, and pet supplies shop.',

            // Homepage Customizer
            'homepage.hero_headline' => 'Your next best friend is waiting.',
            'homepage.hero_supporting' => 'Discover pets, shop food and accessories, and help animals find loving homes—all in one place.',
            'homepage.cta_primary_label' => 'Explore Pets',
            'homepage.cta_primary_url' => '#find-pets',
            'homepage.cta_secondary_label' => 'Shop Pet Supplies',
            'homepage.cta_secondary_url' => '#pet-shop',
            'homepage.cta_adoption_label' => 'Explore Adoption',
            'homepage.cta_adoption_url' => '#adoption-rescue',
            'homepage.announcement_enabled' => true,
            'homepage.announcement_text' => 'Connecting ethical breeders, local shelters, and loving families across the community.',
            'homepage.section_categories_visible' => true,
            'homepage.section_marketplace_visible' => true,
            'homepage.section_shop_visible' => true,
            'homepage.section_adoption_visible' => true,
            'homepage.section_breeds_visible' => true,
            'homepage.section_how_it_works_visible' => true,
            'homepage.section_trust_safety_visible' => true,

            // Single-Shop Commerce & Delivery
            'shop.currency' => 'USD',
            'shop.currency_symbol' => '$',
            'shop.timezone' => 'UTC',
            'shop.cod_enabled' => true,
            'shop.delivery_fee_minor' => 500, // $5.00
            'shop.free_shipping_threshold_minor' => 5000, // $50.00
            'shop.low_stock_threshold' => 5,
            'shop.delivery_zones_info' => '',

            // Optional Feature Flags (Disabled by default)
            'features.multiple_staff_enabled' => false,
            'features.multiple_branches_enabled' => false,
        ];
    }

    /**
     * Retrieve a setting by its key, falling back to defaults or supplied fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        $defaults = self::defaults();
        if (array_key_exists($key, $defaults)) {
            return $defaults[$key];
        }

        return $default;
    }

    /**
     * Retrieve all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        $defaults = self::defaults();

        if (! Schema::hasTable('settings')) {
            self::$memoryCache = $defaults;

            return $defaults;
        }

        $dbSettings = Cache::remember('app_settings_all', 3600, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });

        // Cast boolean / numeric values stored as string if necessary
        $merged = $defaults;
        foreach ($dbSettings as $k => $v) {
            if ($v === 'true' || $v === '1') {
                $merged[$k] = true;
            } elseif ($v === 'false' || $v === '0') {
                $merged[$k] = false;
            } elseif (is_numeric($v) && (str_contains($k, 'minor') || str_contains($k, 'threshold'))) {
                $merged[$k] = (int) $v;
            } else {
                $merged[$k] = $v;
            }
        }

        self::$memoryCache = $merged;

        return $merged;
    }

    /**
     * Store or update a single setting.
     */
    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        $rawValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $rawValue, 'group' => $group]
        );

        $this->flushCache();
    }

    /**
     * Set multiple settings at once.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        $timestamp = now();
        $records = [];

        foreach ($settings as $key => $value) {
            $records[] = [
                'key' => $key,
                'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                'group' => $group,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($records !== []) {
            Setting::query()->upsert($records, ['key'], ['value', 'group', 'updated_at']);
        }

        $this->flushCache();
    }

    /**
     * Clear caches when settings change or tests reset.
     */
    public static function resetCache(): void
    {
        self::$memoryCache = null;
        Cache::forget('app_settings_all');
    }

    public function flushCache(): void
    {
        self::resetCache();
    }
}
