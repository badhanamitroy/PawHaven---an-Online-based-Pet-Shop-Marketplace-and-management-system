<?php

namespace App\Models;

use App\Enums\PaymentRefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PaymentRefundTransaction extends Model
{
    protected $fillable = [
        'payment_transaction_id',
        'submission_key',
        'original_transaction_reference',
        'amount_minor',
        'currency',
        'status',
        'reason',
        'initiated_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'approval_note',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PaymentRefundStatus::class,
            'approved_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund records are changed only through the refund lifecycle service.'));
        static::deleting(fn () => throw new LogicException('Refund records are retained for audit history.'));
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentRefundTransactionEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
