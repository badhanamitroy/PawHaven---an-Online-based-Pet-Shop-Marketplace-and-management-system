<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeSalesDeskService
{
    private const MAX_ORDER_TOTAL_MINOR = 4_294_967_295;

    public function __construct(
        private readonly BusinessAccessService $businessAccess,
        private readonly PaymentTransactionService $payments,
        private readonly InventoryMovementService $inventory,
        private readonly FulfilmentService $fulfilment,
        private readonly PricingService $pricing,
    ) {}

    public function membershipFor(?User $user): BusinessMembership
    {
        if (! $user?->isStaff()) {
            throw new AuthorizationException('An active Employee Seller membership is required.');
        }

        $membership = $this->businessAccess->activeMemberships($user)->first();
        if (! $membership || $membership->role !== BusinessAccessService::EMPLOYEE_SELLER || $membership->branch_id === null) {
            throw new AuthorizationException('An active Employee Seller membership is required.');
        }

        return $membership;
    }

    /** @return Builder<Product> */
    public function eligibleProducts(BusinessMembership $membership): Builder
    {
        return Product::query()->withoutGlobalScopes()
            ->where('company_id', $membership->company_id)
            ->where(function (Builder $query) use ($membership): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $membership->branch_id);
            })
            ->where('is_published', true)
            ->whereNull('archived_at')
            ->whereHas('company', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->where(function (Builder $query): void {
                $query->whereNull('branch_id')->orWhereHas('branch', function (Builder $branch): void {
                    $branch->where('is_active', true)->whereColumn('branches.company_id', 'products.company_id');
                });
            })
            ->where('stock_quantity', '>', 0);
    }

    /** @param array<int|string, int|string> $cart */
    public function createPosOrder(
        User $employeeSeller,
        array $cart,
        array $customer,
        string $submissionKey,
        ?array $paymentSubmission = null,
        ?string $pricingSignature = null,
        ?string $couponCode = null,
        ?string $couponCustomerKey = null,
        ?int $pricingIssuedAt = null,
    ): Order {
        if (Auth::id() !== $employeeSeller->getKey()) {
            throw new AuthorizationException('Sales must be submitted by the authenticated Employee Seller.');
        }

        $membership = $this->membershipFor($employeeSeller);
        $normalizedCart = $this->normalizeCart($cart);
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['submission_key' => 'The checkout session has expired. Please review the cart and try again.']);
        }

        try {
            return DB::transaction(function () use ($employeeSeller, $membership, $normalizedCart, $customer, $submissionKey, $paymentSubmission, $pricingSignature, $couponCode, $couponCustomerKey, $pricingIssuedAt): Order {
                $existing = $this->existingSubmission($submissionKey, $employeeSeller, $membership);
                if ($existing) {
                    return $existing;
                }

                $lockedMembership = BusinessMembership::query()
                    ->whereKey($membership->getKey())
                    ->where('user_id', $employeeSeller->getKey())
                    ->where('company_id', $membership->company_id)
                    ->where('branch_id', $membership->branch_id)
                    ->where('role', BusinessAccessService::EMPLOYEE_SELLER)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $lockedMembership) {
                    throw new AuthorizationException('The Employee Seller assignment is no longer active.');
                }

                $company = Company::query()->withoutGlobalScopes()
                    ->whereKey($lockedMembership->company_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $company) {
                    throw new AuthorizationException('The assigned company is no longer active.');
                }
                $this->businessAccess->installationCompany((int) $company->id);

                $branch = Branch::query()->withoutGlobalScopes()
                    ->whereKey($lockedMembership->branch_id)
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $branch) {
                    throw new AuthorizationException('The assigned branch is no longer active.');
                }

                $products = Product::query()->withoutGlobalScopes()
                    ->whereIn('id', array_keys($normalizedCart))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (Product $product): int => (int) $product->getKey());

                if ($products->count() !== count($normalizedCart)) {
                    throw ValidationException::withMessages(['cart' => 'One or more products are no longer available. Please review the cart.']);
                }

                foreach ($normalizedCart as $productId => $quantity) {
                    /** @var Product|null $product */
                    $product = $products->get((int) $productId);
                    if (! $product
                        || (int) $product->company_id !== (int) $company->id
                        || ($product->branch_id !== null && (int) $product->branch_id !== (int) $branch->id)
                        || ! $product->isPurchasable()) {
                        throw ValidationException::withMessages(['cart' => 'A cart product is not available to this branch. Please review the cart.']);
                    }

                    if ($product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages(['cart' => "There is not enough stock for {$product->name}."]);
                    }
                }

                $resolvedCustomer = $this->resolveCustomer($customer);
                if ($couponCustomerKey !== null && mb_strtolower(trim($couponCustomerKey)) !== mb_strtolower(trim($resolvedCustomer['email']))) {
                    throw ValidationException::withMessages(['coupon_code' => 'The coupon was quoted for a different customer. Apply it again for the current customer.']);
                }
                $quote = $this->pricing->quote(
                    $normalizedCart,
                    SalesChannel::PointOfSale,
                    (int) $branch->id,
                    $couponCode,
                    $resolvedCustomer['user'],
                    $couponCustomerKey ?? $resolvedCustomer['email'],
                    true,
                    $pricingIssuedAt,
                );
                $expectedSignature = $pricingSignature ?? $this->pricing->signature($quote);
                if (! hash_equals($this->pricing->signature($quote), $expectedSignature)) {
                    throw ValidationException::withMessages(['pricing_signature' => 'The cart price changed. Review the updated total before creating the order.']);
                }
                $subtotal = (int) $quote['subtotal_minor'];
                if ($subtotal > self::MAX_ORDER_TOTAL_MINOR) {
                    throw ValidationException::withMessages(['cart' => 'The order total exceeds the amount supported by the current order schema.']);
                }

                $order = new Order([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'order_number' => 'PH-POS-'.strtoupper(Str::random(8)),
                    'user_id' => $resolvedCustomer['user_id'],
                    'customer_name' => $resolvedCustomer['name'],
                    'customer_email' => $resolvedCustomer['email'],
                    'customer_phone' => $resolvedCustomer['phone'],
                    'shipping_address' => 'In-store pickup at '.$branch->name,
                    'shipping_city' => $branch->city ?: $branch->name,
                    'status' => OrderStatus::Pending,
                    'payment_method' => 'pos_pending',
                    'payment_status' => PaymentStatus::Pending,
                    'subtotal_minor' => $subtotal,
                    'delivery_fee_minor' => 0,
                    'total_minor' => $subtotal,
                    'currency' => $quote['currency'],
                    'notes' => $customer['notes'] ?? null,
                    'inventory_reserved_at' => now(),
                    'pricing_snapshot' => $quote,
                    'promotion_discount_minor' => $quote['promotion_discount_minor'],
                    'coupon_discount_minor' => $quote['coupon_discount_minor'],
                ]);
                $order->forceFill([
                    'employee_seller_id' => $employeeSeller->getKey(),
                    'sales_channel' => SalesChannel::PointOfSale,
                    'pos_submission_key' => $submissionKey,
                ])->save();

                foreach ($quote['lines'] as $productId => $line) {
                    /** @var Product $product */
                    $product = $products->get((int) $productId);
                    $item = $order->items()->create([
                        'product_id' => $product->getKey(),
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'price_minor' => $line['unit_price_minor'],
                        'quantity' => $line['quantity'],
                        'subtotal_minor' => $line['line_subtotal_minor'],
                        'base_price_minor' => $line['base_unit_minor'],
                        'resolved_price_minor' => $line['resolved_unit_minor'],
                        'promotion_discount_minor' => $line['promotion_discount_minor'],
                        'coupon_discount_minor' => $line['coupon_discount_minor'],
                        'promotion_id' => $line['promotion_id'],
                        'coupon_id' => $line['coupon_id'],
                    ]);
                    $this->inventory->reserveForOrder($order, $item, $product, $employeeSeller);
                }
                $this->fulfilment->recordPlaced($order, $employeeSeller);
                $this->pricing->recordRedemption($quote, $order, $resolvedCustomer['user'], $resolvedCustomer['email']);

                if ($paymentSubmission !== null && ($paymentSubmission['payment_method'] ?? '') !== '') {
                    $method = PaymentMethod::tryFrom((string) $paymentSubmission['payment_method']);
                    if (! $method || $method === PaymentMethod::OnlineGateway) {
                        throw ValidationException::withMessages(['payment_method' => 'This payment method is not available at the Employee Sales Desk.']);
                    }
                    $amountMinor = $this->payments->amountToMinor((string) $paymentSubmission['payment_amount']);
                    $this->payments->recordPosSubmission(
                        $order,
                        $employeeSeller,
                        $method,
                        $amountMinor,
                        $paymentSubmission['payment_reference'] ?? null,
                        $paymentSubmission['payment_note'] ?? null,
                        $submissionKey,
                    );
                }

                return $order;
            }, 3);
        } catch (QueryException $exception) {
            // The unique key is the final guard for two simultaneous submissions.
            $existing = $this->existingSubmission($submissionKey, $employeeSeller, $membership);
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existingSubmission(string $key, User $employeeSeller, BusinessMembership $membership): ?Order
    {
        $order = Order::query()->withoutGlobalScopes()->where('pos_submission_key', $key)->first();
        if (! $order) {
            return null;
        }

        if ((int) $order->employee_seller_id !== (int) $employeeSeller->getKey()
            || $order->sales_channel !== SalesChannel::PointOfSale
            || (int) $order->company_id !== (int) $membership->company_id
            || (int) $order->branch_id !== (int) $membership->branch_id) {
            throw new AuthorizationException('This checkout reference belongs to another sale.');
        }

        return $order;
    }

    /** @param array<int|string, int|string> $cart
     *  @return array<int, int>
     */
    private function normalizeCart(array $cart): array
    {
        if ($cart === [] || count($cart) > 30) {
            throw ValidationException::withMessages(['cart' => 'Add between one and thirty products before checkout.']);
        }

        $normalized = [];
        foreach ($cart as $productId => $quantity) {
            if (! ctype_digit((string) $productId) || (int) $productId < 1
                || filter_var($quantity, FILTER_VALIDATE_INT) === false
                || (int) $quantity < 1 || (int) $quantity > 10) {
                throw ValidationException::withMessages(['cart' => 'Each product quantity must be between 1 and 10.']);
            }

            $normalized[(int) $productId] = (int) $quantity;
        }

        ksort($normalized);

        return $normalized;
    }

    /** @return array{user_id: int|null, name: string, email: string, phone: string, user: User|null} */
    private function resolveCustomer(array $customer): array
    {
        if (! empty($customer['customer_id'])) {
            $user = User::query()->customers()
                ->whereNotNull('email_verified_at')
                ->lockForUpdate()
                ->find($customer['customer_id']);
            if (! $user) {
                throw ValidationException::withMessages(['customer_id' => 'Choose a verified customer account or enter customer details.']);
            }

            return [
                'user_id' => (int) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $customer['customer_phone'],
                'user' => $user,
            ];
        }

        if (empty($customer['customer_name']) || empty($customer['customer_email'])) {
            throw ValidationException::withMessages(['customer_name' => 'Enter a customer name and email, or select a verified customer account.']);
        }

        return [
            'user_id' => null,
            'name' => $customer['customer_name'],
            'email' => $customer['customer_email'],
            'phone' => $customer['customer_phone'],
            'user' => null,
        ];
    }
}
