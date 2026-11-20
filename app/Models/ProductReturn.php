<?php

namespace App\Models;

use App\Enums\ProductReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProductReturn extends Model
{
    protected $table = 'product_returns';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'company_id',
        'branch_id',
        'activity_branch_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'received_by_user_id',
        'submission_key',
        'receipt_submission_key',
        'status',
        'requested_quantity',
        'received_quantity',
        'resellable_quantity',
        'damaged_quantity',
        'reason',
        'review_note',
        'receipt_note',
        'requested_at',
        'reviewed_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductReturnStatus::class,
            'requested_quantity' => 'integer',
            'received_quantity' => 'integer',
            'resellable_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Return records change only through the return workflow service.'));
        static::deleting(fn () => throw new LogicException('Return records are retained for audit history.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductReturnEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
