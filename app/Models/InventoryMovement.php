<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'company_id',
        'branch_id',
        'activity_branch_id',
        'order_id',
        'order_item_id',
        'product_return_id',
        'actor_user_id',
        'movement_type',
        'quantity_delta',
        'quantity_after',
        'reference_key',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Inventory movements are retained for reconciliation.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function activityBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'activity_branch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
