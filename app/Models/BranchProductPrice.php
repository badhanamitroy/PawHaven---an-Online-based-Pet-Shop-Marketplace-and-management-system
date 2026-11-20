<?php

namespace App\Models;

use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProductPrice extends Model implements BusinessBranchScoped
{
    use HasBusinessScope;

    protected $fillable = ['product_id', 'company_id', 'branch_id', 'price_minor', 'currency'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer'];
    }

    public function businessBranchColumn(): ?string
    {
        return 'branch_id';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
