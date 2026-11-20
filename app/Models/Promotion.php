<?php

namespace App\Models;

use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model implements BusinessBranchScoped
{
    use HasBusinessScope;

    protected $fillable = [
        'company_id', 'branch_id', 'name', 'description', 'discount_type', 'discount_value',
        'minimum_purchase_minor', 'maximum_discount_minor', 'priority', 'sales_channel',
        'stack_with_coupon', 'usage_limit', 'is_active', 'starts_at', 'ends_at', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer', 'minimum_purchase_minor' => 'integer',
            'maximum_discount_minor' => 'integer', 'priority' => 'integer', 'usage_limit' => 'integer',
            'stack_with_coupon' => 'boolean', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    public function businessBranchColumn(): ?string
    {
        return 'branch_id';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'promotion_categories');
    }
}
