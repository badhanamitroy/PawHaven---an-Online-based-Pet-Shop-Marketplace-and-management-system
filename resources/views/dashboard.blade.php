<x-layouts::app :title="__('Customer Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-[#245C45]/20 bg-[#245C45]/10 p-4 text-sm font-medium text-[#173F32]">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Welcome Header (Prompt 5 Greeting) --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#245C45]/10 text-[#245C45]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#245C45]"></span>
                        PawHaven Customer
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-2">
                        Hello, {{ auth()->user()->name }}!
                    </h1>
                    <p class="text-sm text-[#69736B] mt-1 font-medium">
                        Welcome to PawHaven. Let's find your next best friend.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    <a
                        href="{{ route('listings.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                    >
                        <span>Explore Pet Marketplace &rarr;</span>
                    </a>
                    <a
                        href="{{ route('profile.edit') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                        title="Profile Settings"
                    >
                        <svg class="w-4 h-4 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Profile</span>
                    </a>
                    <a
                        href="{{ route('security.edit') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                        title="Security & 2FA"
                    >
                        <svg class="w-4 h-4 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Security</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Metrics Overview --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- My Purchase Requests / Shop Orders --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Shop Orders</span>
                    <div class="w-8 h-8 rounded-lg bg-[#245C45]/10 text-[#245C45] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $orders->total() }}</div>
                    <span class="text-xs text-[#69736B] mt-1 block">Active applications &amp; purchases</span>
                </div>
            </div>

            {{-- Saved / Favourite Pets --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Saved Pets</span>
                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/15 text-[#F28C75] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $savedPets->count() }}</div>
                    <span class="text-xs text-[#F28C75] font-medium mt-1 block">Bookmarked companions</span>
                </div>
            </div>

            {{-- My Pet Listings (Rehoming Posts) --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>My Pet Listings</span>
                    <div class="w-8 h-8 rounded-lg bg-[#245C45]/10 text-[#245C45] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $myListings->count() }}</div>
                    <span class="text-xs text-[#69736B] font-medium mt-1 block">Adoption &amp; rehoming submissions</span>
                </div>
            </div>
        </div>

        {{-- My Purchase Requests & Orders with Timeline & Payment Instructions --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">My Purchase Requests &amp; Orders</h2>
                    <p class="text-xs text-[#69736B]">Track request progression, timeline, and direct in-person payment settlement.</p>
                </div>
                <a href="{{ route('listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    Browse More Pets &rarr;
                </a>
            </div>

            {{-- Ethical Payment Instructions Card --}}
            <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 flex items-start gap-3 text-xs">
                <div class="w-8 h-8 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-[#173F32] block">Safe Payment Instructions (Cash on Handover / COD):</span>
                    <p class="text-[#69736B] mt-0.5 leading-relaxed">
                        To protect animal welfare and prevent fraud, commercial pet settlements occur <strong>strictly in person upon safe handover and physical health inspection</strong>. Never transfer advance funds online. For shelter adoptions, zero fees apply.
                    </p>
                </div>
            </div>

            @if($orders->isEmpty())
                <div class="text-center py-10 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You haven't submitted any pet purchase or adoption requests yet.
                    <div class="mt-3">
                        <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            Explore Available Pets
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($orders as $order)
                        <div class="p-5 rounded-2xl border border-[#202820]/10 bg-white hover:border-[#245C45]/40 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-[#202820]/5">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-[#173F32]">#{{ $order->order_number }}</span>
                                        <span class="text-xs text-[#69736B]">&bull; {{ $order->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <div class="font-semibold text-sm text-[#202820] mt-0.5">
                                        {{ $order->items->first()?->product_name ?? ($order->petListing?->title ?? 'Pet Request') }}
                                    </div>
                                    @if($order->seller)
                                        <div class="text-xs text-[#69736B]">Seller: {{ $order->seller->name }}</div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-[#202820]">
                                            @if($order->total_minor > 0)
                                                {{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}
                                            @else
                                                <span class="text-emerald-700">Free Adoption</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-[#69736B]">
                                            {{ $order->payment_method === 'cod' ? 'Cash on Handover' : ucfirst($order->payment_method) }}
                                        </span>
                                    </div>

                                    {{-- Customer Cancellation if Pending --}}
                                    @if($order->status === \App\Enums\OrderStatus::Pending)
                                        <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors">
                                                Cancel Request
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if($order->items->isNotEmpty())
                                <div class="mt-3 grid gap-1 border-b border-[#202820]/5 pb-3 text-xs text-[#69736B] sm:grid-cols-2">
                                    <div>Merchandise subtotal after discounts: <span class="font-semibold text-[#202820]">{{ $order->currency }} {{ number_format($order->subtotal_minor / 100, 2) }}</span></div>
                                    @if(($order->promotion_discount_minor ?? 0) > 0)<div>Promotion discount: <span class="font-semibold text-emerald-700">−{{ $order->currency }} {{ number_format($order->promotion_discount_minor / 100, 2) }}</span></div>@endif
                                    @if(($order->coupon_discount_minor ?? 0) > 0)<div>Coupon discount: <span class="font-semibold text-emerald-700">−{{ $order->currency }} {{ number_format($order->coupon_discount_minor / 100, 2) }}</span></div>@endif
                                    <div>Delivery: <span class="font-semibold text-[#202820]">{{ $order->delivery_fee_minor === 0 ? 'Free' : $order->currency.' '.number_format($order->delivery_fee_minor / 100, 2) }}</span></div>
                                </div>
                            @endif

                            @if($order->seller_id === null && $order->pet_listing_id === null)
                                @foreach($order->paymentTransactions as $payment)
                                    @if($payment->status === \App\Enums\PaymentTransactionStatus::Verified)
                                        @php
                                            $refundReserved = $payment->refunds
                                                ->whereIn('status', [\App\Enums\PaymentRefundStatus::Requested, \App\Enums\PaymentRefundStatus::Refunded])
                                                ->sum('amount_minor');
                                        @endphp
                                        <div class="mt-3 rounded-xl border border-violet-100 bg-violet-50/60 p-3 text-xs">
                                            <p class="font-semibold text-violet-900">Payment {{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }} · {{ $payment->status->label() }}</p>
                                            @foreach($payment->refunds as $refund)
                                                <p class="mt-1 text-[#69736B]">{{ $refund->status->label() }} · {{ $refund->currency }} {{ number_format($refund->amount_minor / 100, 2) }} @if($refund->approved_by_user_id)— reviewed {{ $refund->approved_at?->format('M d, Y') }}@endif</p>
                                            @endforeach
                                            @if($payment->amount_minor > $refundReserved)
                                                <form method="POST" action="{{ route('orders.refunds.store', $order) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_2fr_auto]">@csrf<input type="hidden" name="payment_transaction_id" value="{{ $payment->id }}"><input type="hidden" name="refund_submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input name="refund_amount" required inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="Amount, max {{ $payment->currency }} {{ number_format(($payment->amount_minor - $refundReserved) / 100, 2) }}" class="rounded-lg border border-violet-200 px-2 py-2"><input name="reason" maxlength="1000" required placeholder="Refund reason" class="rounded-lg border border-violet-200 px-2 py-2"><button class="rounded-lg bg-violet-800 px-3 py-2 font-semibold text-white">Request refund</button></form>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endif

                            @if($order->seller_id === null && $order->pet_listing_id === null)
                                @foreach($order->items as $orderItem)
                                    @if($orderItem->product_id !== null)
                                        @php
                                            $returnUsedQuantity = $orderItem->productReturns->sum(fn ($return) => $return->status === \App\Enums\ProductReturnStatus::Received
                                                ? (int) $return->received_quantity
                                                : ($return->status === \App\Enums\ProductReturnStatus::Rejected ? 0 : (int) $return->requested_quantity));
                                            $returnRemainingQuantity = max(0, (int) $orderItem->quantity - $returnUsedQuantity);
                                        @endphp
                                        <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-xs">
                                            <div class="font-semibold text-amber-900">{{ $orderItem->product_name }} · {{ $returnUsedQuantity }}/{{ $orderItem->quantity }} units requested, approved, or received</div>
                                            @foreach($orderItem->productReturns as $productReturn)
                                                <p class="mt-1 text-[#69736B]">{{ $productReturn->status->label() }} · {{ $productReturn->requested_quantity }} requested@if($productReturn->received_at) · {{ $productReturn->resellable_quantity }} resellable, {{ $productReturn->damaged_quantity }} damaged/non-resellable received@endif</p>
                                            @endforeach
                                            @if($order->status === \App\Enums\OrderStatus::Delivered && $returnRemainingQuantity > 0)
                                                <form method="POST" action="{{ route('orders.items.returns.store', [$order, $orderItem]) }}" class="mt-2 grid gap-2 sm:grid-cols-[auto_1fr_auto]">@csrf
                                                    <input type="hidden" name="return_submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <label class="sr-only" for="return-quantity-{{ $orderItem->id }}">Return quantity</label>
                                                    <input id="return-quantity-{{ $orderItem->id }}" type="number" name="return_quantity" min="1" max="{{ $returnRemainingQuantity }}" required placeholder="Qty (max {{ $returnRemainingQuantity }})" class="rounded-lg border border-amber-200 px-2 py-2">
                                                    <input name="reason" maxlength="1000" required placeholder="Why do you want to return this item?" class="rounded-lg border border-amber-200 px-2 py-2">
                                                    <button class="rounded-lg bg-amber-700 px-3 py-2 font-semibold text-white">Request return</button>
                                                </form>
                                                <p class="mt-2 text-[11px] text-[#69736B]">This request is optional and does not request a refund or change inventory.</p>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endif

                            {{-- Status Timeline & Progress Bar --}}
                            <div class="pt-4">
                                @php
                                    $steps = [
                                        \App\Enums\OrderStatus::Pending->value => 'Pending Review',
                                        \App\Enums\OrderStatus::Processing->value => 'Accepted & Processing',
                                        \App\Enums\OrderStatus::Shipped->value => 'Out for Handover',
                                        \App\Enums\OrderStatus::Delivered->value => 'Completed',
                                    ];

                                    $stepOrder = ['pending', 'processing', 'shipped', 'delivered'];
                                    $currentIndex = array_search($order->status->value, $stepOrder, true);
                                    $isCancelled = $order->status === \App\Enums\OrderStatus::Cancelled;
                                @endphp

                                @if($isCancelled)
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                                        <span>Request Cancelled</span>
                                    </div>
                                @else
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                        @foreach($stepOrder as $idx => $stepKey)
                                            @php
                                                $isPassed = $currentIndex !== false && $idx <= $currentIndex;
                                                $isCurrent = $currentIndex !== false && $idx === $currentIndex;
                                            @endphp
                                            <div class="p-2.5 rounded-xl border {{ $isCurrent ? 'bg-[#245C45] text-white border-[#245C45] font-bold shadow-xs' : ($isPassed ? 'bg-[#245C45]/10 text-[#173F32] border-[#245C45]/20 font-semibold' : 'bg-[#FAF8F2] text-[#69736B] border-[#202820]/10') }}">
                                                <div class="text-[11px] uppercase tracking-wider">Step {{ $idx + 1 }}</div>
                                                <div class="mt-0.5 truncate">{{ $steps[$stepKey] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if($order->company_id !== null && $order->seller_id === null && $order->pet_listing_id === null)
                                <div class="mt-4 rounded-xl border border-[#245C45]/15 bg-[#FAF8F2]/70 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Fulfilment tracking</h3>
                                        @if($order->fulfilment_status)
                                            <span class="rounded-full bg-[#245C45]/10 px-2.5 py-1 text-[11px] font-semibold text-[#245C45]">{{ $order->fulfilment_status->label() }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 space-y-1 text-xs text-[#69736B]">
                                        @if($order->delivery_method)<p>Method: {{ $order->delivery_method->label() }}</p>@endif
                                        @if($order->delivery_provider)<p>Delivery provider: {{ $order->delivery_provider }}</p>@endif
                                        @if($order->tracking_reference)<p>Tracking reference: <span class="font-mono">{{ $order->tracking_reference }}</span></p>@endif
                                        @if($order->dispatched_at)<p>Dispatched: {{ $order->dispatched_at->format('M d, Y g:i A') }}</p>@endif
                                        @if($order->out_for_delivery_at)<p>Out for delivery: {{ $order->out_for_delivery_at->format('M d, Y g:i A') }}</p>@endif
                                        @if($order->delivered_at)<p>Delivered: {{ $order->delivered_at->format('M d, Y g:i A') }}</p>@endif
                                    </div>
                                    @if($order->fulfilmentEvents->isNotEmpty())
                                        <ol class="mt-3 space-y-2 border-l border-[#245C45]/20 pl-3">
                                            @foreach($order->fulfilmentEvents as $event)
                                                <li class="text-xs text-[#69736B]">
                                                    <span class="font-semibold text-[#173F32]">{{ $event->created_at?->format('M d, Y g:i A') }}</span>
                                                    <span class="block">{{ $event->buyer_message }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                    @if($order->fulfilment_status === \App\Enums\FulfilmentStatus::DeliveryFailed && $order->delivery_failed_at)
                                        <p class="mt-2 text-xs text-amber-800">A delivery attempt was unsuccessful. The shop will review the next step.</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="mt-4">
                        {{ $orders->links() }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Saved / Favourite Pets Section --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">Saved Pets (Favorites)</h2>
                    <p class="text-xs text-[#69736B]">Your bookmarked companions for quick access.</p>
                </div>
                <a href="{{ route('listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    Browse More Pets &rarr;
                </a>
            </div>

            @if($savedPets->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You haven't saved any pets to your favorites yet.
                    <div class="mt-3">
                        <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            Explore Pets &amp; Save Favorites
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($savedPets as $pet)
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div>
                                <div class="aspect-video w-full bg-black/10 overflow-hidden relative">
                                    @if($pet->images->isNotEmpty())
                                        <img src="{{ $pet->images->first()->url() }}" alt="{{ $pet->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-3xl">🐾</div>
                                    @endif
                                    <div class="absolute top-2 right-2">
                                        <form method="POST" action="{{ route('listings.favorite', $pet) }}">
                                            @csrf
                                            <button type="submit" class="p-1.5 rounded-full bg-white/90 text-[#F28C75] hover:bg-white shadow-xs" title="Remove from favorites">
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <span class="text-[11px] font-semibold text-[#69736B] uppercase tracking-wider block">
                                        {{ $pet->petCategory?->name ?? 'Pet' }} &bull; {{ $pet->breed?->name ?? 'Mixed' }}
                                    </span>
                                    <a href="{{ route('listings.show', $pet) }}" class="font-bold text-[#173F32] hover:underline block text-sm mt-1">
                                        {{ $pet->title }}
                                    </a>
                                    <div class="mt-2 text-xs font-semibold text-[#202820]">
                                        @if($pet->listing_type === \App\Enums\PetListingType::Sale && $pet->price_minor)
                                            {{ $pet->currency }} {{ number_format($pet->price_minor / 100, 2) }}
                                        @else
                                            <span class="text-[#245C45] font-bold">Free Adoption</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 pt-0">
                                <a href="{{ route('listings.show', $pet) }}" class="w-full text-center py-2 px-3 rounded-xl bg-[#245C45] text-white hover:bg-[#173F32] text-xs font-semibold block transition-colors shadow-xs">
                                    View Pet &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Customer's Own Adoption / Rehoming Posts --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">My Rehoming &amp; Adoption Posts</h2>
                    <p class="text-xs text-[#69736B]">Community posts submitted under your account.</p>
                </div>
            </div>

            @if($myListings->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You have not submitted any pet adoption or rehoming posts.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#202820]/10 text-[#69736B] uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">Title</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($myListings as $listing)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4 font-bold text-[#173F32]">
                                        {{ $listing->title }}
                                    </td>
                                    <td class="py-3 px-4 text-[#202820]">
                                        {{ $listing->petCategory?->name ?? 'Uncategorized' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $listing->status === \App\Enums\PetListingStatus::Published ? 'bg-[#245C45]/10 text-[#173F32]' : 'bg-[#FAF8F2] border border-[#202820]/10 text-[#69736B]' }}">
                                            {{ ucfirst(str_replace('_', ' ', $listing->status->value)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $listing->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        @if($listing->status === \App\Enums\PetListingStatus::Published)
                                            <a href="{{ route('listings.show', $listing) }}" target="_blank" class="text-xs font-semibold text-[#245C45] hover:underline">
                                                View &rarr;
                                            </a>
                                        @else
                                            <span class="text-[11px] text-[#69736B]">In review</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
