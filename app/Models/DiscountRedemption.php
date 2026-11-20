<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DiscountRedemption extends Model
{
    protected $fillable = [
        'order_id', 'promotion_id', 'coupon_id', 'customer_user_id', 'customer_key', 'coupon_code_snapshot',
        'promotion_discount_minor', 'coupon_discount_minor', 'currency',
    ];

    protected function casts(): array
    {
        return ['promotion_discount_minor' => 'integer', 'coupon_discount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Discount redemption records are immutable.'));
        static::deleting(fn () => throw new LogicException('Discount redemption records are retained for audit history.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
