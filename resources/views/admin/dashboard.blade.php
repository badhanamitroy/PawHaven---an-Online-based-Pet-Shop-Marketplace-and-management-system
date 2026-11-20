<x-layouts::admin title="Dashboard" breadcrumbs="1. Overview">
    <div class="space-y-8">
        {{-- Welcome Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs">
            <div>
                <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Single-Shop &amp; Community Operations</span>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-1">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="text-xs sm:text-sm text-[#69736B] mt-1">Real-time database metrics for store orders, pet listings, moderation queue, and community activity.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.homepage.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors">
                    <span>Website Manager</span>
                </a>
                <a href="{{ route('admin.products.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Product</span>
                </a>
            </div>
        </div>

        {{-- Top KPI Metrics Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {{-- 1. Active Commercial Listings --}}
            <a href="{{ route('admin.marketplace.listings.index') }}" class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between hover:border-[#245C45]/40 transition-colors group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Commercial Pets</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $activeCommercialListings }}</div>
                    <span class="text-xs text-emerald-700 font-medium mt-1 block">Active pet sale listings live &rarr;</span>
                </div>
            </a>

            {{-- 2. Pending Purchase Requests --}}
            <a href="{{ route('admin.orders.index') }}" class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between hover:border-amber-400/40 transition-colors group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Buy Requests</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $pendingPurchaseRequests }}</div>
                    <span class="text-xs text-amber-700 font-medium mt-1 block">Pending customer orders &rarr;</span>
                </div>
            </a>

            {{-- 3. Payment Submissions Requiring Review --}}
            <a href="{{ route('admin.orders.index') }}" class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between hover:border-sky-400/40 transition-colors group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Payment Review</span>
                    <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $paymentsRequiringReview }}</div>
                    <span class="text-xs text-sky-700 font-medium mt-1 block">Pending COD / payments &rarr;</span>
                </div>
            </a>

            {{-- 4. Published Adoption Posts --}}
            <a href="{{ route('admin.community.index') }}" class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between hover:border-rose-400/40 transition-colors group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Adoption Posts</span>
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $publishedAdoptionPosts }}</div>
                    <span class="text-xs text-rose-700 font-medium mt-1 block">Published shelter &amp; rescues &rarr;</span>
                </div>
            </a>
        </div>

        {{-- Secondary Operational Row --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            {{-- Moderation Tasks --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Pending Moderation</span>
                <div class="text-2xl font-bold text-[#173F32] mt-2">{{ $pendingModerationTasks }}</div>
                @if($pendingModerationTasks > 0)
                    <a href="{{ route('admin.marketplace.moderation.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#F28C75] hover:underline mt-2">
                        <span>Review queue now &rarr;</span>
                    </a>
                @else
                    <span class="text-xs text-emerald-700 font-medium block mt-2">Queue is clear</span>
                @endif
            </div>

            {{-- Inventory Alerts --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Low Stock Alerts</span>
                <div class="text-2xl font-bold text-[#173F32] mt-2">{{ $lowStockCount }}</div>
                @if($lowStockCount > 0)
                    <a href="{{ route('admin.inventory.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:underline mt-2">
                        <span>Restock items &rarr;</span>
                    </a>
                @else
                    <span class="text-xs text-emerald-700 font-medium block mt-2">Inventory is healthy</span>
                @endif
            </div>

            {{-- Delivered Sales Revenue --}}
            <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">Confirmed Revenue</span>
                <div class="text-2xl font-bold text-[#173F32] mt-2">{{ \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($totalSalesMinor / 100, 2) }}</div>
                <span class="text-xs text-[#69736B] block mt-2">{{ $deliveredOrders }} completed orders</span>
            </div>
        </div>

        {{-- Recent Orders & Moderation Tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Recent Customer Orders --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <div>
                        <h2 class="text-base font-bold text-[#173F32]">Recent Store Orders</h2>
                        <p class="text-xs text-[#69736B]">Latest purchases from the PawHaven Pet Shop.</p>
                    </div>
                    <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                        View All ({{ $totalOrders }}) &rarr;
                    </a>
                </div>

                @if($recentOrders->isEmpty())
                    <div class="text-center py-10 rounded-2xl bg-[#FAF8F2] border border-dashed border-[#202820]/10 text-xs text-[#69736B]">
                        No orders placed yet.
                    </div>
                @else
                    <div class="divide-y divide-[#202820]/5">
                        @foreach($recentOrders as $order)
                            <div class="py-3 flex items-center justify-between text-xs">
                                <div>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-bold text-[#173F32] hover:underline">
                                        #{{ $order->order_number }}
                                    </a>
                                    <div class="text-[#69736B] text-[11px]">{{ $order->customer_name }} &bull; {{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Moderation Trail --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <div>
                        <h2 class="text-base font-bold text-[#173F32]">Recent Moderation Activity</h2>
                        <p class="text-xs text-[#69736B]">Pet listings reviewed by administration.</p>
                    </div>
                    @if(auth()->user()?->isAdmin())
                        <a href="{{ route('admin.marketplace.moderation.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                            Queue &rarr;
                        </a>
                    @endif
                </div>

                @if($recentModerations->isEmpty())
                    <div class="text-center py-10 rounded-2xl bg-[#FAF8F2] border border-dashed border-[#202820]/10 text-xs text-[#69736B]">
                        No recent moderation actions recorded.
                    </div>
                @else
                    <div class="divide-y divide-[#202820]/5">
                        @foreach($recentModerations as $mod)
                            <div class="py-3 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-[#173F32] truncate max-w-[220px]">
                                        {{ $mod->petListing->title ?? 'Listing #'.$mod->pet_listing_id }}
                                    </div>
                                    <div class="text-[#69736B] text-[11px] truncate max-w-[220px]">
                                        {{ $mod->note ?: 'No note provided' }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $mod->action === \App\Enums\ListingModerationActionType::Approved ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                        {{ ucfirst($mod->action->value) }}
                                    </span>
                                    <div class="text-[10px] text-[#69736B] mt-1">{{ $mod->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Audit Trail (Admin Only) --}}
        @if(auth()->user()?->isAdmin())
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <div>
                        <h2 class="text-base font-bold text-[#173F32]">System Audit Trail</h2>
                        <p class="text-xs text-[#69736B]">Permanent record of administrative changes and governance events.</p>
                    </div>
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                        View Full Trail &rarr;
                    </a>
                </div>

                @if($recentAuditLogs->isEmpty())
                    <div class="text-center py-8 rounded-2xl bg-[#FAF8F2] border border-dashed border-[#202820]/10 text-xs text-[#69736B]">
                        No administrative audit logs recorded yet.
                    </div>
                @else
                    <div class="divide-y divide-[#202820]/5">
                        @foreach($recentAuditLogs as $log)
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-[11px] font-bold text-[#245C45] px-2 py-0.5 rounded bg-[#FAF8F2] border border-[#202820]/10">
                                        {{ $log->action }}
                                    </span>
                                    <span class="text-[#202820]">{{ $log->description }}</span>
                                </div>
                                <div class="text-[#69736B] text-[11px] text-right shrink-0">
                                    {{ $log->user->name ?? 'System' }} &bull; {{ $log->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-layouts::admin>
