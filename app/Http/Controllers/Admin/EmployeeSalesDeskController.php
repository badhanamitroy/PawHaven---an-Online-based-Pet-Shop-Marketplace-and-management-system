<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\EmployeeSalesDeskService;
use App\Services\PricingService;
use App\Enums\SalesChannel;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeSalesDeskController extends Controller
{
    private const CART_KEY = 'employee_sales_desk.cart';

    private const SUBMISSION_KEY = 'employee_sales_desk.submission_key';

    private const PRICING_SIGNATURE = 'employee_sales_desk.pricing_signature';

    private const PRICING_ISSUED_AT = 'employee_sales_desk.pricing_issued_at';

    private const COUPON_CODE = 'employee_sales_desk.coupon_code';

    private const COUPON_CUSTOMER = 'employee_sales_desk.coupon_customer';

    public function index(Request $request, EmployeeSalesDeskService $salesDesk, PricingService $pricing): View
    {
        $membership = $salesDesk->membershipFor($request->user());
        $this->ensureSubmissionKey($request);
        $branch = Branch::query()->withoutGlobalScopes()->findOrFail($membership->branch_id);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $products = $salesDesk->eligibleProducts($membership)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matching) use ($search): void {
                    $matching->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->with('category')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();
        $productPrices = [];
        foreach ($products as $product) {
            $productQuote = $pricing->quote(
                [(int) $product->id => 1], SalesChannel::PointOfSale,
                (int) $membership->branch_id,
            );
            $productPrices[$product->id] = [
                'subtotal_minor' => $productQuote['subtotal_minor'],
                'promotion_discount_minor' => $productQuote['promotion_discount_minor'],
            ];
        }

        $cart = (array) $request->session()->get(self::CART_KEY, []);
        $cartProducts = $cart === []
            ? collect()
            : $salesDesk->eligibleProducts($membership)->whereIn('id', array_keys($cart))->with('category')->get()->keyBy('id');
        $couponCode = (string) $request->session()->get(self::COUPON_CODE, '');
        $couponCustomerEmail = (string) $request->session()->get(self::COUPON_CUSTOMER, '');
        $quote = null;
        $pricingError = (string) $request->session()->pull('employee_sales_desk.pricing_error', '');
        $cartSubtotal = 0;
        $pricingSignature = '';
        $pricingIssuedAt = null;
        if ($cart !== [] && $cartProducts->count() === count($cart)) {
            try {
                $quote = $pricing->quote(
                    $cart, SalesChannel::PointOfSale, (int) $branch->id,
                    $couponCode !== '' ? $couponCode : null,
                    null,
                    $couponCustomerEmail !== '' ? $couponCustomerEmail : null,
                );
                $cartSubtotal = (int) $quote['subtotal_minor'];
                $pricingSignature = $pricing->signature($quote);
                $pricingIssuedAt = (int) $quote['issued_at'];
                $request->session()->put([
                    self::PRICING_SIGNATURE => $pricingSignature,
                    self::PRICING_ISSUED_AT => $pricingIssuedAt,
                ]);
            } catch (ValidationException $exception) {
                $pricingError = collect($exception->errors())->flatten()->first() ?? 'The cart could not be priced. Review the items and coupon.';
                $request->session()->forget([self::COUPON_CODE, self::COUPON_CUSTOMER, self::PRICING_SIGNATURE, self::PRICING_ISSUED_AT]);
                $couponCode = '';
                $couponCustomerEmail = '';
            }
        } else {
            $request->session()->forget([self::PRICING_SIGNATURE, self::PRICING_ISSUED_AT]);
        }

        $customerSearch = mb_substr(trim((string) $request->query('customer_search', '')), 0, 100);
        $customers = collect();
        if (mb_strlen($customerSearch) >= 3) {
            $customers = User::query()->customers()->whereNotNull('email_verified_at')
                ->where(function ($query) use ($customerSearch): void {
                    $query->where('name', 'like', '%'.$customerSearch.'%')
                        ->orWhere('email', 'like', '%'.$customerSearch.'%');
                })
                ->orderBy('name')->limit(10)->get(['id', 'name', 'email']);
        }

        return view('admin.sales-desk.index', compact(
            'branch', 'products', 'search', 'cart', 'cartProducts', 'cartSubtotal', 'customerSearch', 'customers',
            'quote', 'pricingSignature', 'pricingIssuedAt', 'couponCode', 'couponCustomerEmail', 'pricingError', 'productPrices'
        ));
    }

    public function applyCoupon(Request $request, EmployeeSalesDeskService $salesDesk, PricingService $pricing): RedirectResponse
    {
        $membership = $salesDesk->membershipFor($request->user());
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:48'],
            'coupon_customer_email' => ['nullable', 'email', 'max:255'],
        ]);
        $code = strtoupper(trim((string) ($validated['coupon_code'] ?? '')));
        if ($code === '') {
            $request->session()->forget([self::COUPON_CODE, self::COUPON_CUSTOMER, self::PRICING_SIGNATURE, self::PRICING_ISSUED_AT]);

            return back()->with('success', 'Coupon removed from this cart.');
        }
        $cart = (array) $request->session()->get(self::CART_KEY, []);
        $customerKey = trim((string) ($validated['coupon_customer_email'] ?? ''));
        $quote = $pricing->quote($cart, SalesChannel::PointOfSale, (int) $membership->branch_id, $code, null, $customerKey ?: null);
        $request->session()->put(self::COUPON_CODE, $code);
        $request->session()->put(self::COUPON_CUSTOMER, $customerKey);
        $request->session()->put(self::PRICING_SIGNATURE, $pricing->signature($quote));
        $request->session()->put(self::PRICING_ISSUED_AT, (int) $quote['issued_at']);

        return back()->with('success', 'Coupon applied to the current cart.');
    }

    public function addProduct(Request $request, int $product, EmployeeSalesDeskService $salesDesk): RedirectResponse
    {
        $membership = $salesDesk->membershipFor($request->user());
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        $product = $salesDesk->eligibleProducts($membership)->whereKey($product)->first();
        if (! $product) {
            abort(404);
        }

        $cart = (array) $request->session()->get(self::CART_KEY, []);
        $newQuantity = (int) ($cart[$product->id] ?? 0) + (int) $validated['quantity'];
        if ($newQuantity > 10 || $newQuantity > $product->stock_quantity) {
            return back()->withErrors(['cart' => 'The requested quantity exceeds the available stock or per-item limit.']);
        }
        if (! array_key_exists($product->id, $cart) && count($cart) >= 30) {
            return back()->withErrors(['cart' => 'A cart can contain up to thirty different products.']);
        }

        $cart[$product->id] = $newQuantity;
        $request->session()->put(self::CART_KEY, $cart);
        $this->ensureSubmissionKey($request);

        return back()->with('success', 'Product added to the cart.');
    }

    public function updateQuantity(Request $request, int $productId, EmployeeSalesDeskService $salesDesk): RedirectResponse
    {
        $membership = $salesDesk->membershipFor($request->user());
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        $product = $salesDesk->eligibleProducts($membership)->whereKey($productId)->first();
        if (! $product || (int) $validated['quantity'] > $product->stock_quantity) {
            return back()->withErrors(['cart' => 'This product is no longer available in the requested quantity.']);
        }

        $cart = (array) $request->session()->get(self::CART_KEY, []);
        if (! array_key_exists($productId, $cart)) {
            abort(404);
        }
        $cart[$productId] = (int) $validated['quantity'];
        $request->session()->put(self::CART_KEY, $cart);

        return back()->with('success', 'Cart quantity updated.');
    }

    public function removeProduct(Request $request, int $productId): RedirectResponse
    {
        $cart = (array) $request->session()->get(self::CART_KEY, []);
        unset($cart[$productId]);
        $request->session()->put(self::CART_KEY, $cart);

        return back()->with('success', 'Product removed from the cart.');
    }

    public function store(Request $request, EmployeeSalesDeskService $salesDesk): RedirectResponse
    {
        $salesDesk->membershipFor($request->user());
        $validated = Validator::make($request->all(), [
            'submission_key' => ['required', 'uuid'],
            'pricing_signature' => ['nullable', 'string', 'size:64'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_without:customer_id'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'in:cash,card,bkash,nagad,rocket'],
            'payment_amount' => ['nullable', 'required_with:payment_method', 'string', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'payment_reference' => ['nullable', 'string', 'max:120', 'required_if:payment_method,card,bkash,nagad,rocket'],
            'payment_note' => ['nullable', 'string', 'max:500'],
            'confirmed' => ['accepted'],
        ])->validate();

        $expectedKey = (string) $request->session()->get(self::SUBMISSION_KEY, '');
        if ($expectedKey === '' || ! hash_equals($expectedKey, (string) $validated['submission_key'])) {
            return back()->withErrors(['submission_key' => 'This checkout page has expired. Review the cart and try again.']);
        }
        $expectedPricing = (string) $request->session()->get(self::PRICING_SIGNATURE, '');
        $expectedPricingIssuedAt = $request->session()->get(self::PRICING_ISSUED_AT);
        $submittedPricing = (string) ($validated['pricing_signature'] ?? $expectedPricing);
        if ($expectedPricing === '' || $expectedPricingIssuedAt === null || ! hash_equals($expectedPricing, $submittedPricing)) {
            $membership = $salesDesk->membershipFor($request->user());
            $cart = (array) $request->session()->get(self::CART_KEY, []);
            if ($cart === [] || $salesDesk->eligibleProducts($membership)->whereIn('id', array_keys($cart))->count() !== count($cart)) {
                return back()->withErrors(['cart' => 'A cart product is no longer available for this branch. Review the cart.']);
            }

            return back()->withErrors(['pricing_signature' => 'The cart price changed. Review the updated price before creating the order.']);
        }

        $order = $salesDesk->createPosOrder(
            $request->user(),
            (array) $request->session()->get(self::CART_KEY, []),
            $validated,
            $expectedKey,
            ! empty($validated['payment_method']) ? $validated : null,
            $expectedPricing,
            $request->session()->get(self::COUPON_CODE),
            $request->session()->get(self::COUPON_CUSTOMER),
            (int) $expectedPricingIssuedAt,
        );

        $request->session()->forget([self::CART_KEY, self::PRICING_SIGNATURE, self::PRICING_ISSUED_AT, self::COUPON_CODE, self::COUPON_CUSTOMER]);
        $request->session()->put(self::SUBMISSION_KEY, (string) Str::uuid());

        return redirect()->route('admin.sales-desk.receipt', $order);
    }

    public function receipt(Request $request, int $order, EmployeeSalesDeskService $salesDesk): View
    {
        $salesDesk->membershipFor($request->user());
        $sale = Order::query()->withoutGlobalScopes()
            ->whereKey($order)
            ->where('employee_seller_id', $request->user()->getKey())
            ->where('sales_channel', 'pos')
            ->with(['items', 'branch', 'paymentTransactions.recordedBy', 'paymentTransactions.verifiedBy'])
            ->firstOrFail();

        return view('admin.sales-desk.receipt', compact('sale'));
    }

    private function ensureSubmissionKey(Request $request): void
    {
        $key = (string) $request->session()->get(self::SUBMISSION_KEY, '');
        if (! Str::isUuid($key)) {
            $request->session()->put(self::SUBMISSION_KEY, (string) Str::uuid());
        }
    }
}
