<x-layouts::app :title="__('Customer Purchase & Adoption Requests')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 text-xs font-semibold text-[#69736B] uppercase tracking-wider">
                        <a href="{{ route('seller.dashboard') }}" class="hover:underline">Seller Dashboard</a>
                        <span>&bull;</span>
                        <span>Purchase &amp; Adoption Requests</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-1">
                        Customer Requests
                    </h1>
                    <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                        Respond to customer applications, verify handover/COD payments, and update order lifecycles.
                    </p>
                </div>

                <div>
                    <a
                        href="{{ route('seller.dashboard') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                    >
                        <span>&larr; Back to Dashboard</span>
                    </a>
                </div>
            </div>

            {{-- Filter Pills --}}
            <div class="flex items-center gap-2 mt-6 overflow-x-auto pb-1 text-xs">
                @php
                    $filters = [
                        'all' => 'All (' . $counts['all'] . ')',
                        'pending' => 'New / Pending (' . $counts['pending'] . ')',
                        'awaiting' => 'Awaiting Action (' . $counts['awaiting'] . ')',
                        'processing' => 'Processing (' . $counts['processing'] . ')',
                        'shipped' => 'Shipped / Handover (' . $counts['shipped'] . ')',
                        'delivered' => 'Completed (' . $counts['delivered'] . ')',
                        'cancelled' => 'Cancelled (' . $counts['cancelled'] . ')',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    <a
                        href="{{ route('seller.requests.index', ['status' => $key]) }}"
                        class="px-3 py-1.5 rounded-full font-medium transition-colors whitespace-nowrap {{ $currentFilter === $key && empty($paymentFilter) ? 'bg-[#245C45] text-white shadow-xs' : 'bg-[#FAF8F2] text-[#69736B] hover:bg-[#202820]/10 hover:text-[#202820]' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach

                <a
                    href="{{ route('seller.requests.index', ['payment' => 'pending']) }}"
                    class="px-3 py-1.5 rounded-full font-medium transition-colors whitespace-nowrap {{ $paymentFilter === 'pending' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}"
                >
                    Payment Verification Needed ({{ $counts['pending_payment'] }})
                </a>
            </div>
        </div>

        {{-- Requests Table --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
            @if($orders->isEmpty())
                <div class="text-center py-12 text-[#69736B]">
                    <p class="text-base font-semibold text-[#202820]">No customer requests match this filter.</p>
                    <p class="text-xs mt-1">When prospective buyers or adopters apply for your listings, they will show up here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#202820]/10 text-[#69736B] uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">Request #</th>
                                <th class="py-3 px-4">Subject / Listing</th>
                                <th class="py-3 px-4">Customer Contact</th>
                                <th class="py-3 px-4">Location</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4">Request Status</th>
                                <th class="py-3 px-4">Payment</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($orders as $order)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4 font-mono font-medium text-[#173F32]">
                                        <a href="{{ route('seller.requests.show', $order) }}" class="hover:underline">
                                            #{{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-[#202820]">
                                            {{ $order->items->first()?->product_name ?? ($order->petListing?->title ?? 'Pet Request') }}
                                        </div>
                                        @if($order->petListing)
                                            <span class="text-[11px] text-[#69736B] block">Listing #{{ $order->petListing->id }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-[#202820] font-medium">{{ $order->customer_name }}</div>
                                        <div class="text-[11px] text-[#69736B]">{{ $order->customer_phone }}</div>
                                        <div class="text-[11px] text-[#69736B]">{{ $order->customer_email }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $order->shipping_city }}
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-[#202820]">
                                        @if($order->total_minor > 0)
                                            {{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}
                                        @else
                                            <span class="text-emerald-700 font-semibold">Free Adoption</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-amber-100 text-amber-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'shipped' => 'bg-purple-100 text-purple-800',
                                                'delivered' => 'bg-emerald-100 text-emerald-800',
                                                'cancelled' => 'bg-rose-100 text-rose-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusColors[$order->status->value] ?? 'bg-zinc-100 text-zinc-700' }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                                            {{ $order->payment_status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="{{ route('seller.requests.show', $order) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[#245C45] text-white hover:bg-[#173F32] font-semibold text-xs shadow-xs transition-colors">
                                            <span>Manage</span>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
