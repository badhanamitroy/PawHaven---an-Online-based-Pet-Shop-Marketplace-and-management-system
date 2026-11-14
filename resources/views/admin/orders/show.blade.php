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
                                        ${{ number_format($item->price_minor / 100, 2) }} &times; {{ $item->quantity }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right font-bold text-xs text-[#173F32]">
                                ${{ number_format($item->subtotal_minor / 100, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Financial Summary --}}
                <div class="border-t border-gray-100 bg-[#FAF8F2]/60 p-6 space-y-2 text-xs">
                    <div class="flex justify-between text-[#69736B]">
                        <span>Subtotal</span>
                        <span class="font-medium text-[#202820]">${{ number_format($order->subtotal_minor / 100, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-[#69736B]">
                        <span>Delivery Fee</span>
                        <span class="font-medium text-[#202820]">
                            @if($order->delivery_fee_minor > 0)
                                ${{ number_format($order->delivery_fee_minor / 100, 2) }}
                            @else
                                <span class="text-emerald-700 font-bold">FREE</span>
                            @endif
                        </span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between text-sm font-bold text-[#173F32]">
                        <span>Total</span>
                        <span>${{ number_format($order->total_minor / 100, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Actions & Customer Info (1 col) --}}
        <div class="space-y-6">
            {{-- Status Lifecycle Controls --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Order Lifecycle
                </h3>

                @if(count($allowedTransitions) > 0)
                    <div class="space-y-3">
                        <p class="text-xs text-[#69736B]">Advance order status:</p>
                        @foreach($allowedTransitions as $trans)
                            @if($trans !== \App\Enums\OrderStatus::Cancelled)
                                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $trans->value }}" />
                                    <input name="reason" maxlength="500" placeholder="Reason (required for Branch Managers)" class="mb-2 w-full rounded-xl border border-gray-200 px-2 py-1.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none" />
                                    <button
                                        type="submit"
                                        class="w-full rounded-xl bg-[#245C45] py-2 text-xs font-semibold text-white hover:bg-[#173F32] transition-colors"
                                    >
                                        Mark as {{ $trans->label() }} &rarr;
                                    </button>
                                </form>
                            @endif
                        @endforeach

                        {{-- Cancel action --}}
                        @if(in_array(\App\Enums\OrderStatus::Cancelled, $allowedTransitions, true))
                            <div class="border-t border-gray-100 pt-3">
                                <details class="group">
                                    <summary class="cursor-pointer text-xs font-semibold text-red-600 hover:text-red-700">
                                        Cancel this order...
                                    </summary>
                                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="mt-2 space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="cancelled" />
                                        <textarea
                                            name="cancelled_reason"
                                            rows="2"
                                            required
                                            placeholder="Reason for cancellation (required)..."
                                            class="w-full rounded-xl border border-gray-200 p-2 text-xs text-[#202820] focus:border-red-500 focus:outline-none"
                                        ></textarea>
                                        <button
                                            type="submit"
                                            class="w-full rounded-xl bg-red-600 py-1.5 text-xs font-semibold text-white hover:bg-red-700 transition-colors"
                                        >
                                            Confirm Cancellation
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 p-3 text-center text-xs text-[#69736B]">
                        This order has reached its final state (<strong>{{ $order->status->label() }}</strong>).
                    </div>
                @endif
            </div>

            {{-- Payment Details --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Payment Information
                </h3>
                <div class="text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-[#69736B]">Method:</span>
                        <span class="font-bold text-[#202820] uppercase">{{ $order->payment_method }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[#69736B]">Status:</span>
                        <span class="font-bold text-[#173F32]">{{ $order->payment_status->label() }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.orders.update-payment', $order) }}" class="pt-2 border-t border-gray-100 flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <select
                        name="payment_status"
                        class="flex-1 rounded-xl border border-gray-200 px-2 py-1.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                    >
                        @foreach(\App\Enums\PaymentStatus::cases() as $payStatus)
                            <option value="{{ $payStatus->value }}" {{ $order->payment_status === $payStatus ? 'selected' : '' }}>
                                {{ $payStatus->label() }}
                            </option>
                        @endforeach
                    </select>
                    <input name="reason" maxlength="500" placeholder="Reason (required for Branch Managers)" class="flex-1 rounded-xl border border-gray-200 px-2 py-1.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none" />
                    <button
                        type="submit"
                        class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-3 py-1.5 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                    >
                        Save
                    </button>
                </form>
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
