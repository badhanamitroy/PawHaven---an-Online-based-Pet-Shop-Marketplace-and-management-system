<x-layouts::app :title="__('Manage Request #' . $order->order_number)">
    <div class="flex h-full w-full flex-1 flex-col gap-6 max-w-5xl mx-auto">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/50 dark:text-rose-300">
                {{ session('error') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="inline-flex items-center gap-2 text-xs font-semibold text-[#69736B] uppercase tracking-wider">
                <a href="{{ route('seller.dashboard') }}" class="hover:underline">Seller Dashboard</a>
                <span>&bull;</span>
                <a href="{{ route('seller.requests.index') }}" class="hover:underline">Customer Requests</a>
                <span>&bull;</span>
                <span>#{{ $order->order_number }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-2">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32]">
                        Request #{{ $order->order_number }}
                    </h1>
                    <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                        Submitted on {{ $order->created_at->format('F d, Y \a\t h:i A') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @php
                        $statusColors = [
                            'pending' => 'bg-amber-100 text-amber-800',
                            'processing' => 'bg-blue-100 text-blue-800',
                            'shipped' => 'bg-purple-100 text-purple-800',
                            'delivered' => 'bg-emerald-100 text-emerald-800',
                            'cancelled' => 'bg-rose-100 text-rose-800',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $statusColors[$order->status->value] ?? 'bg-zinc-100 text-zinc-700' }}">
                        {{ $order->status->label() }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Details & Actions (2 cols) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Requested Pet / Item --}}
                <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#173F32] mb-4">
                        Requested Listing Details
                    </h2>

                    @if($order->petListing)
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                            @if($order->petListing->images->isNotEmpty())
                                <img src="{{ $order->petListing->images->first()->url() }}" alt="{{ $order->petListing->title }}" class="w-20 h-20 rounded-xl object-cover border border-[#202820]/10 shrink-0">
                            @else
                                <div class="w-20 h-20 rounded-xl bg-white border border-[#202820]/10 flex items-center justify-center text-2xl text-[#69736B] shrink-0">
                                    🐾
                                </div>
                            @endif
                            <div class="flex-1">
                                <a href="{{ route('listings.show', $order->petListing) }}" target="_blank" class="text-base font-bold text-[#173F32] hover:underline block">
                                    {{ $order->petListing->title }}
                                </a>
                                <p class="text-xs text-[#69736B] mt-0.5">
                                    Category: {{ $order->petListing->petCategory?->name ?? 'Uncategorized' }} &bull; Breed: {{ $order->petListing->breed?->name ?? 'Mixed' }}
                                </p>
                                <div class="mt-2 text-sm font-semibold text-[#202820]">
                                    @if($order->total_minor > 0)
                                        Price: ${{ number_format($order->total_minor / 100, 2) }}
                                    @else
                                        <span class="text-emerald-700">Free Ethical Adoption / Rehoming</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-xs">
                            @foreach($order->items as $item)
                                <div class="flex justify-between py-1">
                                    <span class="font-medium text-[#202820]">{{ $item->product_name }}</span>
                                    <span class="font-semibold">${{ number_format($item->subtotal_minor / 100, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Customer Notes --}}
                    @if($order->notes)
                        <div class="mt-4 p-4 rounded-2xl bg-zinc-50 border border-zinc-200">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 block mb-1">Customer Application Notes:</span>
                            <p class="text-xs text-zinc-800 whitespace-pre-line leading-relaxed">{{ $order->notes }}</p>
                        </div>
                    @endif
                </div>

                {{-- Status Progression Controls --}}
                <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#173F32] mb-2">
                        Manage Request Lifecycle
                    </h2>
                    <p class="text-xs text-[#69736B] mb-4">
                        Progress this request according to verified pet handover standards.
                    </p>

                    @if(in_array($order->status, [\App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled], true))
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-xs text-[#69736B]">
                            This request is in terminal status (<strong>{{ $order->status->label() }}</strong>). No further stage transitions are permitted.
                        </div>
                    @else
                        <div class="flex items-center gap-3 flex-wrap">
                            {{-- Pending -> Processing --}}
                            @if($order->status === \App\Enums\OrderStatus::Pending)
                                <form method="POST" action="{{ route('seller.requests.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="processing">
                                    <button type="submit" class="px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-[#245C45] hover:bg-[#173F32] shadow-xs">
                                        &check; Accept &amp; Mark Processing
                                    </button>
                                </form>
                            @endif

                            {{-- Processing -> Shipped --}}
                            @if($order->status === \App\Enums\OrderStatus::Processing)
                                <form method="POST" action="{{ route('seller.requests.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="shipped">
                                    <button type="submit" class="px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-purple-700 hover:bg-purple-800 shadow-xs">
                                        Mark Ready for Pickup / Out for Delivery &rarr;
                                    </button>
                                </form>
                            @endif

                            {{-- Shipped -> Delivered --}}
                            @if($order->status === \App\Enums\OrderStatus::Shipped)
                                <form method="POST" action="{{ route('seller.requests.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 shadow-xs">
                                        &check; Confirm Final Handover / Delivered
                                    </button>
                                </form>
                            @endif

                            {{-- Decline / Cancel --}}
                            <form method="POST" action="{{ route('seller.requests.status', $order) }}" onsubmit="return confirm('Are you sure you want to decline or cancel this customer request?');">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100">
                                    Decline / Cancel Request
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right Column: Customer Info & Payment Verification (1 col) --}}
            <div class="space-y-6">
                {{-- Customer Info Card --}}
                <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#173F32]">
                        Customer Contact
                    </h2>

                    <div>
                        <span class="text-xs text-[#69736B] block">Applicant Name</span>
                        <span class="text-sm font-bold text-[#202820]">{{ $order->customer_name }}</span>
                    </div>

                    <div>
                        <span class="text-xs text-[#69736B] block">Phone Number</span>
                        <a href="tel:{{ $order->customer_phone }}" class="text-sm font-medium text-[#245C45] hover:underline">
                            {{ $order->customer_phone }}
                        </a>
                    </div>

                    <div>
                        <span class="text-xs text-[#69736B] block">Email Address</span>
                        <a href="mailto:{{ $order->customer_email }}" class="text-sm font-medium text-[#245C45] hover:underline">
                            {{ $order->customer_email }}
                        </a>
                    </div>

                    <div>
                        <span class="text-xs text-[#69736B] block">Handover / Delivery Address</span>
                        <p class="text-xs text-[#202820] mt-0.5 leading-relaxed">
                            {{ $order->shipping_address }}<br>
                            {{ $order->shipping_city }}
                        </p>
                    </div>
                </div>

                {{-- Payment Verification Card --}}
                <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs space-y-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#173F32]">
                        Payment &amp; Settlement
                    </h2>

                    <div class="flex items-center justify-between">
                        <span class="text-xs text-[#69736B]">Method</span>
                        <span class="text-xs font-bold uppercase text-[#202820]">
                            {{ $order->payment_method === 'cod' ? 'Cash on Handover / COD' : ucfirst($order->payment_method) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-xs text-[#69736B]">Payment Status</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $order->payment_status->label() }}
                        </span>
                    </div>

                    @if($order->payment_reference)
                        <div>
                            <span class="text-xs text-[#69736B] block">Payment Reference</span>
                            <span class="text-xs font-mono font-medium text-[#202820]">{{ $order->payment_reference }}</span>
                        </div>
                    @endif

                    @if($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                        <form method="POST" action="{{ route('seller.requests.payment', $order) }}" class="pt-3 border-t border-[#202820]/10 space-y-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="payment_status" value="paid">

                            <div>
                                <label for="payment_reference" class="block text-[11px] font-semibold text-[#69736B] mb-1">
                                    Receipt / Transaction ID (Optional)
                                </label>
                                <input
                                    type="text"
                                    name="payment_reference"
                                    id="payment_reference"
                                    placeholder="e.g. CASH-HANDOVER-01"
                                    class="w-full px-3 py-1.5 rounded-lg border border-[#202820]/15 text-xs bg-[#FAF8F2] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                                >
                            </div>

                            <button type="submit" class="w-full py-2 px-3 rounded-xl text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 transition-colors shadow-xs">
                                &check; Verify &amp; Record Payment
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
