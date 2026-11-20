<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderFulfilmentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'company_id',
        'branch_id',
        'actor_user_id',
        'event_type',
        'from_status',
        'to_status',
        'submission_key',
        'payload_hash',
        'internal_note',
        'buyer_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => FulfilmentStatus::class,
            'to_status' => FulfilmentStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fulfilment events are append-only.'));
        static::deleting(fn () => throw new LogicException('Fulfilment events are retained for audit history.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
