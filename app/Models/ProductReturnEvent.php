<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductReturnEvent extends Model
{
    protected $table = 'product_return_events';

    public $timestamps = false;

    protected $fillable = [
        'product_return_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Return history events are append-only.'));
        static::deleting(fn () => throw new LogicException('Return history events are retained for audit history.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'product_return_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
