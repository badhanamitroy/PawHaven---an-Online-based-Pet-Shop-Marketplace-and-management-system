<?php

namespace App\Services;

use App\Facades\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Policy for the application's current two-decimal, single-currency money model. */
class CurrencyPolicy
{
    public const SUPPORTED = ['USD', 'BDT', 'CAD', 'EUR'];

    public function current(): string
    {
        $currency = strtoupper((string) Settings::get('shop.currency', 'USD'));
        $this->assertSupported($currency);

        return $currency;
    }

    public function assertSupported(string $currency): void
    {
        if (! in_array(strtoupper($currency), self::SUPPORTED, true)) {
            throw ValidationException::withMessages([
                'currency' => 'Choose a supported two-decimal currency: USD, BDT, CAD, or EUR.',
            ]);
        }
    }

    public function assertCanSet(string $currency): void
    {
        $currency = strtoupper($currency);
        $this->assertSupported($currency);
        if (($existing = $this->storedCurrencies()) !== [] && $existing !== [$currency]) {
            throw ValidationException::withMessages([
                'currency' => 'Currency cannot be changed after monetary records exist. Existing amounts retain their stored currency; use a reviewed data migration before changing it.',
            ]);
        }
    }

    public function assertCanCreateIn(string $currency): void
    {
        $currency = strtoupper($currency);
        $this->assertSupported($currency);
        if ($currency !== $this->current()) {
            throw ValidationException::withMessages([
                'currency' => 'The current shop currency does not match the currency of this monetary record.',
            ]);
        }
        if (($existing = $this->storedCurrencies()) !== [] && $existing !== [$currency]) {
            throw ValidationException::withMessages([
                'currency' => 'New monetary records must use the currency already stored for this installation.',
            ]);
        }
    }

    /** @return list<string> */
    private function storedCurrencies(): array
    {
        $currencies = [];
        foreach (['products', 'pet_listings', 'orders'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency')) {
                $currencies = array_merge(
                    $currencies,
                    DB::table($table)->distinct()->pluck('currency')->map(fn ($value): string => strtoupper((string) $value))->all(),
                );
            }
        }
        $currencies = array_values(array_unique($currencies));
        sort($currencies);

        return $currencies;
    }
}
