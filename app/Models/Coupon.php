<?php

namespace App\Models;

use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coupon extends Model implements BusinessBranchScoped
{
    use HasBusinessScope;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'discount_type', 'discount_value', 'minimum_purchase_minor',
        'maximum_discount_minor', 'usage_limit', 'per_customer_limit', 'sales_channel', 'is_active',
        'starts_at', 'ends_at', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer', 'minimum_purchase_minor' => 'integer', 'maximum_discount_minor' => 'integer',
            'usage_limit' => 'integer', 'per_customer_limit' => 'integer', 'is_active' => 'boolean',
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    public function businessBranchColumn(): ?string
    {
        return 'branch_id';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'coupon_categories');
    }
}
