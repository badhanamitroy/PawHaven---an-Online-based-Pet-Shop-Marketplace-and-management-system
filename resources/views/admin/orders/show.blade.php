@extends('layouts.admin')

@section('title', 'Order #' . $order->order_number)

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[#69736B]">
                <a href="{{ route('admin.orders.index') }}" class="hover:text-[#245C45]">Orders</a>
                <span>&rsaquo;</span>
                <span class="font-mono">#{{ $order->order_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Order #{{ $order->order_number }}</h1>
                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $order->status->badgeClasses() }}">
                    {{ $order->status->label() }}
                </span>
            </div>
            <p class="text-xs text-[#69736B] mt-1">Placed on {{ $order->created_at?->format('F d, Y \a\t h:i A') }}</p>
        </div>
        <div>
            <a
                href="{{ route('admin.orders.index') }}"
                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                &larr; Back to Orders
            </a>
        </div>
    </div>

    @if($order->status === \App\Enums\OrderStatus::Cancelled && $order->cancelled_reason)
        <div class="rounded-2xl border border-red-200 bg-red-50/70 p-4 text-xs text-red-800">
            <span class="font-bold">Cancellation Reason:</span> {{ $order->cancelled_reason }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Items & Totals (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Order Items Table --}}
            <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-[#FAF8F2] px-6 py-3.5">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Ordered Items ({{ $order->items->count() }})</h2>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($order->items as $item)
                        <div class="p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                @if($item->product && $item->product->image_path)
                                    <img src="{{ $item->product->image_path }}" alt="{{ $item->product_name }}" class="h-12 w-12 rounded-lg object-cover border border-gray-200" />
                                @else
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-gray-400 border border-gray-200">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    </div>
                                @endif
                                <div>
                                    <div class="font-bold text-xs text-[#173F32]">
                                        @if($item->product)
                                            <a href="{{ route('admin.products.edit', $item->product) }}" class="hover:underline">
                                                {{ $item->product_name }}
                                            </a>
                                        @else
                                            {{ $item->product_name }}
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-[#69736B] font-mono">SKU: {{ $item->sku }}</div>
                                    <div class="text-[11px] text-[#69736B]">
                                        {{ $order->currency }} {{ number_format($item->price_minor / 100, 2) }} &times; {{ $item->quantity }}
                                    </div>
                                    @if(($item->promotion_discount_minor ?? 0) > 0 || ($item->coupon_discount_minor ?? 0) > 0)
                                        <div class="mt-1 text-[11px] text-emerald-700">
                                            @if(($item->promotion_discount_minor ?? 0) > 0)<span>Promotion −{{ $order->currency }} {{ number_format($item->promotion_discount_minor / 100, 2) }}</span>@endif
                                            @if(($item->coupon_discount_minor ?? 0) > 0)<span class="ml-2">Coupon −{{ $order->currency }} {{ number_format($item->coupon_discount_minor / 100, 2) }}</span>@endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right font-bold text-xs text-[#173F32]">
                                {{ $order->currency }} {{ number_format($item->subtotal_minor / 100, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Financial Summary --}}
                <div class="border-t border-gray-100 bg-[#FAF8F2]/60 p-6 space-y-2 text-xs">
                    @if(($order->promotion_discount_minor ?? 0) + ($order->coupon_discount_minor ?? 0) > 0)
                    <div class="flex justify-between text-[#69736B]"><span>Merchandise before discounts</span><span>{{ $order->currency }} {{ number_format(($order->subtotal_minor + $order->promotion_discount_minor + $order->coupon_discount_minor) / 100, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between text-[#69736B]">
                        <span>Subtotal after discounts</span>
                        <span class="font-medium text-[#202820]">{{ $order->currency }} {{ number_format($order->subtotal_minor / 100, 2) }}</span>
                    </div>
                    @if(($order->promotion_discount_minor ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Promotion discounts</span><span>−{{ $order->currency }} {{ number_format($order->promotion_discount_minor / 100, 2) }}</span></div>@endif
                    @if(($order->coupon_discount_minor ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Coupon discounts</span><span>−{{ $order->currency }} {{ number_format($order->coupon_discount_minor / 100, 2) }}</span></div>@endif
                    <div class="flex justify-between text-[#69736B]">
                        <span>Delivery Fee</span>
                        <span class="font-medium text-[#202820]">
                            @if($order->delivery_fee_minor > 0)
                                {{ $order->currency }} {{ number_format($order->delivery_fee_minor / 100, 2) }}
                            @else
                                <span class="text-emerald-700 font-bold">FREE</span>
                            @endif
                        </span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between text-sm font-bold text-[#173F32]">
                        <span>Total</span>
                        <span>{{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</span>
                    </div>
                </div>
            </div>

            @if($order->seller_id === null && $order->pet_listing_id === null)
                <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Physical product returns</h2>
                            <p class="mt-1 text-[11px] text-[#69736B]">Return approval does not change stock or create a refund. Record physical receipt and condition separately.</p>
                        </div>
                    </div>
                    @forelse($order->productReturns as $return)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs space-y-2">
                            <div class="flex flex-wrap justify-between gap-2 font-semibold text-[#173F32]">
                                <span>{{ $return->status->label() }} · {{ $return->product?->name ?? $return->orderItem?->product_name }}</span>
                                <span>{{ $return->received_quantity }} received / {{ $return->requested_quantity }} requested</span>
                            </div>
                            <p class="text-[#69736B]">Requested by {{ $return->requestedBy?->name ?? 'Unavailable' }}: {{ $return->reason }}</p>
                            @if($return->reviewed_at)
                                <p class="text-[#69736B]">Reviewed by {{ $return->reviewedBy?->name ?? 'Unavailable' }} · {{ $return->reviewed_at->format('M j, Y g:i A') }}: {{ $return->review_note }}</p>
                            @endif
                            @if($return->received_at)
                                <p class="text-[#69736B]">Received by {{ $return->receivedBy?->name ?? 'Unavailable' }} · {{ $return->received_at->format('M j, Y g:i A') }}. Resellable: {{ $return->resellable_quantity }}; damaged/non-resellable: {{ $return->damaged_quantity }}. {{ $return->receipt_note }}</p>
                            @endif
                            @if($return->events->isNotEmpty())
                                <details><summary class="cursor-pointer text-[#245C45]">Return history</summary><ul class="mt-2 space-y-1 text-[#69736B]">@foreach($return->events as $event)<li>{{ $event->created_at?->format('M j, Y g:i A') }} — {{ $event->actor?->name ?? 'Unavailable' }}: {{ $event->from_status ?? 'created' }} → {{ $event->to_status }}@if($event->note) · {{ $event->note }}@endif</li>@endforeach</ul></details>
                            @endif
                            @if($canReviewReturns && $return->status === \App\Enums\ProductReturnStatus::Requested)
                                <div class="grid gap-2 sm:grid-cols-2 border-t border-gray-200 pt-3">
                                    <form method="POST" action="{{ route('admin.returns.approve', $return) }}" class="space-y-2">@csrf<textarea name="review_note" maxlength="1000" required placeholder="Approval note; no stock change yet" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea><button class="w-full rounded-lg bg-emerald-700 px-3 py-2 font-semibold text-white">Approve physical return</button></form>
                                    <form method="POST" action="{{ route('admin.returns.reject', $return) }}" class="space-y-2">@csrf<textarea name="review_note" maxlength="1000" required placeholder="Reason for rejection" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea><button class="w-full rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">Reject return</button></form>
                                </div>
                            @elseif($canReviewReturns && $return->status === \App\Enums\ProductReturnStatus::Approved)
                                <form method="POST" action="{{ route('admin.returns.receive', $return) }}" class="grid gap-2 border-t border-gray-200 pt-3 sm:grid-cols-3">@csrf
                                    <input type="hidden" name="receipt_submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <label class="text-[#69736B]">Physically received<input type="number" name="received_quantity" min="1" max="{{ $return->requested_quantity }}" required class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5"></label>
                                    <label class="text-[#69736B]">Resellable<input type="number" name="resellable_quantity" min="0" max="{{ $return->requested_quantity }}" required value="0" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5"></label>
                                    <label class="text-[#69736B]">Damaged / non-resellable<input type="number" name="damaged_quantity" min="0" max="{{ $return->requested_quantity }}" required value="0" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5"></label>
                                    <textarea name="receipt_note" maxlength="1000" required placeholder="Condition and receipt evidence" class="sm:col-span-2 w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea>
                                    <button class="self-end rounded-lg bg-[#245C45] px-3 py-2 font-semibold text-white">Record physical receipt</button>
                                    <p class="sm:col-span-3 text-[11px] text-[#69736B]">The resellable and damaged quantities must equal the physically received quantity. Only resellable units return to available stock.</p>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-[#69736B]">No physical return requests for this order.</p>
                    @endforelse
                </div>
            @endif
        </div>

        {{-- Right: Actions & Customer Info (1 col) --}}
        <div class="space-y-6">
            {{-- Fulfilment Lifecycle Controls --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Fulfilment
                </h3>

                @if($order->fulfilment_status)
                    <p class="rounded-lg bg-[#FAF8F2] p-3 text-xs text-[#173F32]">
                        <span class="font-semibold">{{ $order->fulfilment_status->label() }}</span>
                        @if($order->delivery_method) · {{ $order->delivery_method->label() }} @endif
                    </p>
                    @if($order->delivery_provider || $order->tracking_reference)
                        <div class="text-xs text-[#69736B]">
                            @if($order->delivery_provider)<p>Provider: {{ $order->delivery_provider }}</p>@endif
                            @if($order->tracking_reference)<p>Tracking reference: {{ $order->tracking_reference }}</p>@endif
                        </div>
                    @endif
                @endif

                @forelse($fulfilmentActions as $action)
                    @php
                        $noteRequired = $membership?->role === \App\Services\BusinessAccessService::MANAGER
                            || in_array($action, [\App\Enums\FulfilmentAction::ReportShortage, \App\Enums\FulfilmentAction::ResumePicking, \App\Enums\FulfilmentAction::RecordFailedAttempt, \App\Enums\FulfilmentAction::ReturnToSender, \App\Enums\FulfilmentAction::Cancel], true);
                    @endphp
                    <form method="POST" action="{{ route('admin.fulfilment.transition', $order) }}" class="space-y-2 border-t border-gray-100 pt-3">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action->value }}">
                        <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        @if($action === \App\Enums\FulfilmentAction::Confirm)
                            <label class="block text-[11px] text-[#69736B]">Delivery method
                                <select name="delivery_method" required class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                                    @foreach(\App\Enums\DeliveryMethod::cases() as $method)
                                        <option value="{{ $method->value }}" @selected($order->delivery_method === $method)>{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        @if($action === \App\Enums\FulfilmentAction::StartPicking && ! $order->delivery_method)
                            <label class="block text-[11px] text-[#69736B]">Delivery method for this existing order
                                <select name="delivery_method" required class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                                    <option value="">Select method</option>
                                    @foreach(\App\Enums\DeliveryMethod::cases() as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        @if($action === \App\Enums\FulfilmentAction::ReportShortage)
                            <label class="block text-[11px] text-[#69736B]">Unavailable order item
                                <select name="order_item_id" required class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                                    <option value="">Select item</option>
                                    @foreach($order->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->product_name }} · ordered {{ $item->quantity }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        @if($action === \App\Enums\FulfilmentAction::Pack)
                            <label class="flex items-start gap-2 text-[11px] text-[#69736B]"><input type="checkbox" name="items_verified" value="1" required class="mt-0.5 rounded border-gray-300"> I verified every order item and quantity. Picking does not deduct stock again.</label>
                        @endif

                        @if($action === \App\Enums\FulfilmentAction::Dispatch && $order->delivery_method === \App\Enums\DeliveryMethod::ExternalCourier)
                            <input name="delivery_provider" maxlength="120" required placeholder="Courier/provider name" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                            <input name="tracking_reference" maxlength="120" placeholder="Tracking / consignment reference" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                        @endif

                        @if($noteRequired || in_array($action, [\App\Enums\FulfilmentAction::Pack, \App\Enums\FulfilmentAction::Dispatch], true))
                            <textarea name="note" maxlength="1000" {{ $noteRequired ? 'required' : '' }} rows="2" placeholder="{{ $noteRequired ? 'Operational note / reason (required)' : 'Optional packing or dispatch note' }}" class="w-full rounded-lg border border-gray-200 p-2 text-xs"></textarea>
                        @endif

                        <button type="submit" class="w-full rounded-xl {{ $action === \App\Enums\FulfilmentAction::Cancel ? 'bg-rose-700 hover:bg-rose-800' : 'bg-[#245C45] hover:bg-[#173F32]' }} py-2 text-xs font-semibold text-white transition-colors">
                            {{ $action->label() }} →
                        </button>
                    </form>
                @else
                    <div class="rounded-xl bg-gray-50 p-3 text-center text-xs text-[#69736B]">
                        No fulfilment action is available at this stage.
                    </div>
                @endforelse

                @if($order->fulfilmentEvents->isNotEmpty())
                    <details class="border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-xs font-semibold text-[#245C45]">Fulfilment event history</summary>
                        <ol class="mt-2 space-y-2 text-[11px] text-[#69736B]">
                            @foreach($order->fulfilmentEvents as $event)
                                <li class="rounded-lg bg-gray-50 p-2">
                                    <div>{{ $event->created_at?->format('M j, Y g:i A') }} · {{ str($event->event_type)->replace('_', ' ')->title() }} · {{ $event->actor?->name ?? 'System / unavailable' }}</div>
                                    @if($event->from_status || $event->to_status)<div>{{ $event->from_status?->label() ?? 'Created' }} → {{ $event->to_status->label() }}</div>@endif
                                    @if($event->internal_note)<div class="mt-1 text-[#202820]">Internal note: {{ $event->internal_note }}</div>@endif
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            </div>

            {{-- Payment Details --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Payment Information
                </h3>
                <div class="text-xs space-y-2">
                    @if($order->paymentTransactions->isEmpty())
                    <div class="flex justify-between">
                        <span class="text-[#69736B]">Method:</span>
                        <span class="font-bold text-[#202820] uppercase">{{ $order->payment_method }}</span>
                    </div>
                    @else
                    <p class="text-[11px] text-[#69736B]">Order channel: {{ $order->sales_channel?->value ?? 'Online' }}. Payment methods are shown on each transaction below.</p>
                    @endif
                    <div class="flex justify-between items-center">
                        <span class="text-[#69736B]">Status:</span>
                        <span class="font-bold text-[#173F32]">{{ $order->payment_status->label() }}</span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3 text-xs space-y-1.5">
                    <div class="flex justify-between"><span class="text-[#69736B]">Recorded amount</span><strong>{{ $order->currency }} {{ number_format($paymentTotals['recorded_minor'] / 100, 2) }}</strong></div>
                    <div class="flex justify-between"><span class="text-[#69736B]">Gross verified</span><strong>{{ $order->currency }} {{ number_format($paymentTotals['verified_minor'] / 100, 2) }}</strong></div>
                    <div class="flex justify-between"><span class="text-[#69736B]">Successful refunds</span><strong>{{ $order->currency }} {{ number_format($paymentTotals['refunded_minor'] / 100, 2) }}</strong></div>
                    <div class="flex justify-between"><span class="text-[#69736B]">Net collected</span><strong>{{ $order->currency }} {{ number_format($paymentTotals['net_collected_minor'] / 100, 2) }}</strong></div>
                    <div class="flex justify-between"><span class="text-[#69736B]">Outstanding balance after refunds</span><strong>{{ $order->currency }} {{ number_format($paymentTotals['outstanding_minor'] / 100, 2) }}</strong></div>
                    <p class="pt-1 text-[11px] text-[#69736B]">Recorded payments remain uncollected until verified. Order delivery does not change payment status.</p>
                </div>
                @if($order->seller_id !== null || $order->pet_listing_id !== null)
                    <p class="border-t border-gray-100 pt-3 text-[11px] text-[#69736B]">This is a Marketplace Seller request. Its payment receipt is managed through the assigned Seller's request workflow, separately from PawHaven branch collections.</p>
                @endif

                @if($order->paymentTransactions->isNotEmpty())
                    <div class="space-y-3 border-t border-gray-100 pt-3">
                        @foreach($order->paymentTransactions as $payment)
                            <div class="rounded-xl bg-gray-50 p-3 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-[#202820]">{{ $payment->payment_method->label() }} · {{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</p>
                                        @if($payment->transaction_reference)<p class="mt-1 break-all font-mono text-[#69736B]">Ref: {{ $payment->transaction_reference }}</p>@endif
                                        <p class="mt-1 text-[#69736B]">Recorded by {{ $payment->recordedBy?->name ?? 'Unavailable' }} · {{ $payment->created_at?->format('M j, Y g:i A') }}</p>
                                        @if($payment->verified_at)<p class="mt-1 text-emerald-800">Verified by {{ $payment->verifiedBy?->name ?? 'Unavailable' }} · {{ $payment->verified_at->format('M j, Y g:i A') }}</p>@endif
                                    </div>
                                    <span class="shrink-0 rounded-full bg-white px-2 py-1 font-semibold text-[#173F32]">{{ $payment->status->label() }}</span>
                                </div>
                                @if($payment->events->isNotEmpty())
                                    <details class="mt-2"><summary class="cursor-pointer text-[#245C45]">Payment history</summary><ul class="mt-2 space-y-1">@foreach($payment->events as $event)<li>{{ $event->created_at?->format('M j, Y g:i A') }} — {{ $event->actor?->name ?? 'Unavailable' }}: {{ $event->from_status ?? 'created' }} → {{ $event->to_status }}@if($event->note) ({{ $event->note }})@endif</li>@endforeach</ul></details>
                                @endif
                                @if($payment->refunds->isNotEmpty())
                                    <div class="mt-3 space-y-2 border-t border-gray-200 pt-3">
                                        @foreach($payment->refunds as $refund)
                                            <div class="rounded-lg border border-violet-100 bg-violet-50/70 p-3">
                                                <div class="flex flex-wrap justify-between gap-2 font-semibold"><span>{{ $refund->status->label() }} · {{ $refund->currency }} {{ number_format($refund->amount_minor / 100, 2) }}</span><span>{{ $refund->created_at?->format('M j, Y g:i A') }}</span></div>
                                                <p class="mt-1 text-[#69736B]">Requested by {{ $refund->initiatedBy?->name ?? 'Unavailable' }}: {{ $refund->reason }}</p>
                                                @if($refund->approved_by_user_id)<p class="mt-1 text-[#69736B]">Reviewed by {{ $refund->approvedBy?->name ?? 'Unavailable' }} · {{ $refund->approved_at?->format('M j, Y g:i A') }}@if($refund->approval_note) — {{ $refund->approval_note }}@endif</p>@endif
                                                @if($refund->events->isNotEmpty())<details class="mt-2"><summary class="cursor-pointer text-[#245C45]">Refund history</summary><ul class="mt-2 space-y-1">@foreach($refund->events as $event)<li>{{ $event->created_at?->format('M j, Y g:i A') }} — {{ $event->actor?->name ?? 'Unavailable' }}: {{ $event->from_status ?? 'created' }} → {{ $event->to_status }}@if($event->note) ({{ $event->note }})@endif</li>@endforeach</ul></details>@endif
                                                @if($canReviewRefunds && $refund->status === \App\Enums\PaymentRefundStatus::Requested && (int) $refund->initiated_by_user_id !== (int) auth()->id())
                                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                        <form method="POST" action="{{ route('admin.refunds.complete', $refund) }}" class="space-y-2">@csrf<textarea name="approval_note" maxlength="1000" required placeholder="Confirm refund issued and record evidence" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea><button class="w-full rounded-lg bg-emerald-700 px-3 py-2 font-semibold text-white">Confirm refund issued</button></form>
                                                        <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}" class="space-y-2">@csrf<textarea name="approval_note" maxlength="1000" required placeholder="Reason for rejection" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea><button class="w-full rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">Reject request</button></form>
                                                    </div>
                                                @elseif($refund->status === \App\Enums\PaymentRefundStatus::Requested && (int) $refund->initiated_by_user_id === (int) auth()->id())
                                                    <p class="mt-2 text-amber-800">A different authorized reviewer must complete or reject this request.</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if($canManagePayments && $payment->status === \App\Enums\PaymentTransactionStatus::Submitted)
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @if((int) $payment->recorded_by_user_id !== (int) auth()->id())<form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="space-y-2">@csrf<input name="note" maxlength="500" required placeholder="Verification evidence / reason" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"><button class="w-full rounded-lg bg-emerald-700 px-3 py-2 font-semibold text-white">Verify collection</button></form>@else<p class="rounded-lg bg-amber-50 p-2 text-amber-800">Another authorized person must verify this submission.</p>@endif
                                        <form method="POST" action="{{ route('admin.payments.fail', $payment) }}" class="space-y-2">@csrf<input name="note" maxlength="500" required placeholder="Failure reason" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"><button class="w-full rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">Mark failed</button></form>
                                    </div>
                                @endif
                                @php
                                    $refundReserved = $payment->refunds
                                        ->whereIn('status', [\App\Enums\PaymentRefundStatus::Requested, \App\Enums\PaymentRefundStatus::Refunded])
                                        ->sum('amount_minor');
                                @endphp
                                @if($canRequestRefund && $payment->status === \App\Enums\PaymentTransactionStatus::Verified && $payment->amount_minor > $refundReserved)
                                    <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}" class="mt-3 space-y-2 border-t border-gray-200 pt-3">@csrf<input type="hidden" name="payment_transaction_id" value="{{ $payment->id }}"><input type="hidden" name="refund_submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><p class="font-semibold">Request partial or full refund</p><input name="refund_amount" required inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="Amount ({{ $payment->currency }}), max {{ $payment->currency }} {{ number_format(($payment->amount_minor - $refundReserved) / 100, 2) }}" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"><textarea name="reason" maxlength="1000" required placeholder="Refund reason" class="w-full rounded-lg border border-gray-200 px-2 py-1.5"></textarea><button class="w-full rounded-lg border border-violet-700 px-3 py-2 font-semibold text-violet-800">Submit for independent review</button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($canManagePayments && $order->status !== \App\Enums\OrderStatus::Cancelled && $paymentTotals['available_to_record_minor'] > 0)
                    <form method="POST" action="{{ route('admin.orders.payments.store', $order) }}" class="space-y-2 border-t border-gray-100 pt-3">
                        @csrf
                        <input type="hidden" name="payment_submission_key" value="{{ old('payment_submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <p class="text-xs font-semibold text-[#173F32]">Record payment submission</p>
                        <select name="payment_method" required class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">@foreach(\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select>
                        <input name="payment_amount" required inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="Amount ({{ $order->currency }}), max {{ $order->currency }} {{ number_format($paymentTotals['available_to_record_minor'] / 100, 2) }}" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                        <input name="payment_reference" maxlength="120" placeholder="Transaction reference (required except cash)" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs">
                        <textarea name="note" maxlength="500" rows="2" placeholder="Recording note (Branch Managers must provide a reason)" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"></textarea>
                        <button class="w-full rounded-lg border border-[#245C45] px-3 py-2 text-xs font-semibold text-[#245C45]">Submit for verification</button>
                    </form>
                @endif
            </div>

            {{-- Shipping & Customer Details --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Delivery &amp; Customer
                </h3>
                <div class="text-xs space-y-2 text-[#202820]">
                    <div>
                        <div class="font-bold text-sm text-[#173F32]">{{ $order->customer_name }}</div>
                        <div class="text-[#69736B]">{{ $order->customer_email }}</div>
                        @if($order->customer_phone)
                            <div class="text-[#69736B]">{{ $order->customer_phone }}</div>
                        @endif
                    </div>

                    <div class="border-t border-gray-100 pt-2">
                        <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Shipping Address</span>
                        <div class="text-gray-700 leading-relaxed">
                            {{ $order->shipping_address }}<br />
                            {{ $order->shipping_city }}@if($order->shipping_state), {{ $order->shipping_state }}@endif @if($order->shipping_postal_code){{ $order->shipping_postal_code }}@endif
                        </div>
                    </div>

                    @if($order->notes)
                        <div class="border-t border-gray-100 pt-2">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Customer Notes</span>
                            <p class="italic text-gray-600 bg-gray-50 p-2 rounded-lg">{{ $order->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
