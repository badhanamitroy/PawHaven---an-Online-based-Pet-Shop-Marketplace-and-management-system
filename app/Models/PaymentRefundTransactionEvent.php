<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentRefundTransactionEvent extends Model
{
    protected $table = 'payment_refund_events';

    public $timestamps = false;

    protected $fillable = [
        'payment_refund_transaction_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund history events are append-only.'));
        static::deleting(fn () => throw new LogicException('Refund history events are retained for audit history.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefundTransaction::class, 'payment_refund_transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
