<?php

namespace App\Facades;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array<string, mixed> all()
 * @method static void set(string $key, mixed $value, string $group = 'general')
 * @method static void setMany(array<string, mixed> $settings, string $group = 'general')
 * @method static void flushCache()
 *
 * @see SettingsService
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsService::class;
    }
}
