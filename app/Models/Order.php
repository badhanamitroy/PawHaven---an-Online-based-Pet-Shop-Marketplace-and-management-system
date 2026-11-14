<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $company_id
 * @property int|null $branch_id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string $shipping_address
 * @property string $shipping_city
 * @property string|null $shipping_state
 * @property string|null $shipping_postal_code
 * @property OrderStatus $status
 * @property string $payment_method
 * @property PaymentStatus $payment_status
 * @property int $subtotal_minor
 * @property int $delivery_fee_minor
 * @property int $total_minor
 * @property Carbon|null $inventory_reserved_at
 * @property int|null $seller_id
 * @property int|null $pet_listing_id
 * @property string|null $payment_reference
 * @property string|null $notes
 * @property string|null $cancelled_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $seller
 * @property-read PetListing|null $petListing
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model implements BusinessBranchScoped
{
    use HasBusinessScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'order_number',
        'user_id',
        'seller_id',
        'pet_listing_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'subtotal_minor',
        'delivery_fee_minor',
        'total_minor',
        'inventory_reserved_at',
        'notes',
        'cancelled_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'inventory_reserved_at' => 'datetime',
        ];
    }

    public function businessBranchColumn(): ?string
    {
        return 'branch_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<PetListing, $this>
     */
    public function petListing(): BelongsTo
    {
        return $this->belongsTo(PetListing::class, 'pet_listing_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
