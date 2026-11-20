<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentTransactionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_transaction_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment history events are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment history events are retained for audit history.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
