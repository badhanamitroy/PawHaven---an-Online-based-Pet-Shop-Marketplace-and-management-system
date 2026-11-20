<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Enums\DeliveryMethod;
use App\Enums\FulfilmentStatus;
use App\Models\Concerns\BusinessBranchScoped;
use App\Models\Concerns\HasBusinessScope;
use App\Services\BusinessAccessService;
use App\Services\CurrencyPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
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
 * @property int|null $employee_seller_id
 * @property SalesChannel $sales_channel
 * @property int|null $pet_listing_id
 * @property string|null $payment_reference
 * @property string|null $notes
 * @property string|null $cancelled_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $seller
 * @property-read User|null $employeeSeller
 * @property-read PetListing|null $petListing
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model implements BusinessBranchScoped
{
    use HasBusinessScope;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $actor = Auth::user();
            $currency = strtoupper((string) ($order->getAttribute('currency') ?: app(CurrencyPolicy::class)->current()));
            app(CurrencyPolicy::class)->assertCanCreateIn($currency);
            $order->setAttribute('currency', $currency);
            $order->setAttribute(
                'sales_channel',
                $order->getAttribute('sales_channel') ?? SalesChannel::Online->value,
            );

            if (! $actor instanceof User || ! $actor->isStaff()) {
                $order->setAttribute('employee_seller_id', null);

                return;
            }

            $membership = app(BusinessAccessService::class)->activeMemberships($actor)->first();
            $order->setAttribute(
                'employee_seller_id',
                $membership?->role === BusinessAccessService::EMPLOYEE_SELLER ? $actor->id : null,
            );
        });
    }

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
        'currency',
        'inventory_reserved_at',
        'online_submission_key',
        'notes',
        'cancelled_reason',
        'fulfilment_status',
        'delivery_method',
        'delivery_provider',
        'tracking_reference',
        'packing_notes',
        'confirmed_at',
        'picking_started_at',
        'picked_at',
        'packed_at',
        'ready_at',
        'dispatched_at',
        'out_for_delivery_at',
        'delivery_failed_at',
        'delivered_at',
        'delivery_attempt_count',
        'delivery_failed_reason',
        'pricing_snapshot',
        'promotion_discount_minor',
        'coupon_discount_minor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'sales_channel' => SalesChannel::class,
            'subtotal_minor' => 'integer',
            'delivery_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'promotion_discount_minor' => 'integer',
            'coupon_discount_minor' => 'integer',
            'pricing_snapshot' => 'array',
            'inventory_reserved_at' => 'datetime',
            'fulfilment_status' => FulfilmentStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'confirmed_at' => 'datetime',
            'picking_started_at' => 'datetime',
            'picked_at' => 'datetime',
            'packed_at' => 'datetime',
            'ready_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_attempt_count' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function employeeSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_seller_id');
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

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function productReturns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function fulfilmentEvents(): HasMany
    {
        return $this->hasMany(OrderFulfilmentEvent::class, 'order_id')->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
