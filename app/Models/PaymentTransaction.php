<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'submission_key',
        'transaction_reference',
        'reference_hash',
        'payment_method',
        'amount_minor',
        'currency',
        'status',
        'source',
        'recorded_by_user_id',
        'verified_by_user_id',
        'verified_at',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'source' => PaymentSource::class,
            'status' => PaymentTransactionStatus::class,
            'amount_minor' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment transactions are changed only through the payment lifecycle service.'));
        static::deleting(fn () => throw new LogicException('Payment transaction records are retained for audit history.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentTransactionEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefundTransaction::class)->orderBy('created_at')->orderBy('id');
    }
}
