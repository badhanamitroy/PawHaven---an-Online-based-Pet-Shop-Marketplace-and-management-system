<x-layouts::admin title="Operations overview" breadcrumbs="Overview / Dashboard">
    <main style="flex:1;min-width:0;padding:24px 32px 40px;box-sizing:border-box;display:flex;flex-direction:column;gap:20px">
        {{-- Dashboard Header --}}
        <header style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <div style="font-size:12px;color:#5B6460;letter-spacing:.02em">Overview / Dashboard</div>
                <h1 style="margin:2px 0 0;font-weight:700;font-size:24px;letter-spacing:-.01em;color:#101814">Operations overview</h1>
                <div style="font-size:13px;color:#5B6460;margin-top:2px">Store orders, listings, moderation and community activity</div>
            </div>

            {{-- Search Control (submits to orders search if staff or admin) --}}
            @if(auth()->user()?->isStaff() || auth()->user()?->isAdmin())
                <form action="{{ route('admin.orders.index') }}" method="GET" style="display:flex;align-items:center;margin:0">
                    <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #E6E2D8;border-radius:10px;padding:0 14px;min-height:44px;box-sizing:border-box;min-width:220px;cursor:text">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5B6460" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="m20 20-3.5-3.5"/>
                        </svg>
                        <input
                            type="search"
                            name="search"
                            placeholder="Search orders, pets, users"
                            aria-label="Search"
                            style="border:0;outline:0;background:transparent;font:inherit;font-size:14px;width:100%"
                        >
                    </label>
                </form>
            @endif

            {{-- Shortcuts --}}
            @if(auth()->user()?->isAdmin())
                <a class="btn" href="{{ route('admin.homepage.index') }}" style="background:#fff;border:1px solid #E6E2D8;color:#1B2420">Website Manager</a>
            @endif

            @can('create', \App\Models\Product::class)
                <a class="btn" href="{{ route('admin.products.create') }}" style="background:#17603F;color:#fff">+ Add Product</a>
            @endcan
        </header>

        {{-- Top Section: Confirmed Revenue & Needs Your Attention --}}
        <section class="top">
            {{-- 1. Confirmed Revenue Card --}}
            <div class="card" style="padding:24px;display:flex;flex-direction:column;gap:14px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">CONFIRMED REVENUE</span>
                    <a href="{{ route('public.home') }}" target="_blank" style="font-size:13px;font-weight:600">View public store ↗</a>
                </div>
                <div style="display:flex;align-items:baseline;gap:10px">
                    <span style="font-size:34px;font-weight:700;color:#101814;letter-spacing:-.02em">
                        {{ \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($totalSalesMinor / 100, 2) }}
                    </span>
                    <span style="font-size:13px;color:#5B6460">{{ $deliveredOrders }} completed orders</span>
                </div>
                @if($deliveredOrders > 0)
                    <svg viewBox="0 0 400 70" preserveAspectRatio="none" style="width:100%;height:70px" role="img" aria-label="Revenue trend, confirmed sales">
                        <path d="M0 60 Q 100 48, 200 32 T 400 14" fill="none" stroke="#17603F" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="400" cy="14" r="4" fill="#17603F"/>
                    </svg>
                    <div style="font-size:13px;color:#5B6460">{{ $deliveredOrders }} completed order{{ $deliveredOrders === 1 ? '' : 's' }} delivering confirmed revenue.</div>
                @else
                    <svg viewBox="0 0 400 70" preserveAspectRatio="none" style="width:100%;height:70px" role="img" aria-label="Revenue trend, no data yet">
                        <line x1="0" y1="55" x2="400" y2="55" stroke="#C9D8CF" stroke-width="2" stroke-dasharray="6 6"/>
                    </svg>
                    <div style="font-size:13px;color:#5B6460">Revenue will chart here once the first order is completed.</div>
                @endif
            </div>

            {{-- 2. Needs Your Attention Card --}}
            <div class="card" style="padding:24px;display:flex;flex-direction:column;gap:12px">
                <div style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">NEEDS YOUR ATTENTION</div>

                {{-- Moderation item --}}
                @if($pendingModerationTasks > 0)
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#FFFBEB;border:1px solid #FDE68A">
                        <span style="width:28px;height:28px;border-radius:50%;background:#D97706;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700" aria-hidden="true">!</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px;color:#92400E">{{ $pendingModerationTasks }} listing{{ $pendingModerationTasks === 1 ? '' : 's' }} need review</div>
                            <div style="font-size:13px;color:#B45309">{{ $pendingModerationTasks }} pending pet listing{{ $pendingModerationTasks === 1 ? '' : 's' }}</div>
                        </div>
                        @if(auth()->user()?->isAdmin())
                            <a href="{{ route('admin.marketplace.moderation.index') }}" style="font-size:13px;font-weight:600;color:#B45309">Open</a>
                        @endif
                    </div>
                @else
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#EEF5F0">
                        <span style="width:28px;height:28px;border-radius:50%;background:#17603F;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px" aria-hidden="true">✓</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px">Moderation queue is clear</div>
                            <div style="font-size:13px;color:#5B6460">0 pending pet listings</div>
                        </div>
                        @if(auth()->user()?->isAdmin())
                            <a href="{{ route('admin.marketplace.moderation.index') }}" style="font-size:13px;font-weight:600">Open</a>
                        @endif
                    </div>
                @endif

                {{-- Inventory item --}}
                @if($lowStockCount > 0)
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#FEF2F2;border:1px solid #FECACA">
                        <span style="width:28px;height:28px;border-radius:50%;background:#DC2626;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700" aria-hidden="true">!</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px;color:#991B1B">{{ $lowStockCount }} low stock alert{{ $lowStockCount === 1 ? '' : 's' }}</div>
                            <div style="font-size:13px;color:#B91C1C">Restock needed for depleted inventory</div>
                        </div>
                        @can('viewAny', \App\Models\Product::class)
                            <a href="{{ route('admin.inventory.index') }}" style="font-size:13px;font-weight:600;color:#B91C1C">Open</a>
                        @endcan
                    </div>
                @else
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#EEF5F0">
                        <span style="width:28px;height:28px;border-radius:50%;background:#17603F;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px" aria-hidden="true">✓</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px">Inventory is healthy</div>
                            <div style="font-size:13px;color:#5B6460">0 low stock alerts</div>
                        </div>
                        @can('viewAny', \App\Models\Product::class)
                            <a href="{{ route('admin.inventory.index') }}" style="font-size:13px;font-weight:600">Open</a>
                        @endcan
                    </div>
                @endif

                {{-- Payments review item --}}
                @if($paymentsRequiringReview > 0)
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#EFF6FF;border:1px solid #BFDBFE">
                        <span style="width:28px;height:28px;border-radius:50%;background:#2563EB;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700" aria-hidden="true">!</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px;color:#1E40AF">{{ $paymentsRequiringReview }} payment{{ $paymentsRequiringReview === 1 ? '' : 's' }} to review</div>
                            <div style="font-size:13px;color:#1D4ED8">{{ $paymentsRequiringReview }} pending COD / payment{{ $paymentsRequiringReview === 1 ? '' : 's' }}</div>
                        </div>
                        <a href="{{ route('admin.orders.index') }}" style="font-size:13px;font-weight:600;color:#1D4ED8">Open</a>
                    </div>
                @else
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:#EEF5F0">
                        <span style="width:28px;height:28px;border-radius:50%;background:#17603F;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px" aria-hidden="true">✓</span>
                        <div style="flex:1">
                            <div style="font-weight:600;font-size:14px">No payments to review</div>
                            <div style="font-size:13px;color:#5B6460">0 pending COD / payments</div>
                        </div>
                        <a href="{{ route('admin.orders.index') }}" style="font-size:13px;font-weight:600">Open</a>
                    </div>
                @endif
            </div>
        </section>

        {{-- KPI Cards Section (4 Columns Responsive) --}}
        <section class="kpis">
            {{-- 1. Commercial Pets --}}
            <div class="card" style="padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">COMMERCIAL PETS</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#17603F"></span>
                </div>
                <div style="font-size:34px;font-weight:700;margin:10px 0 4px;color:#12392A">{{ $activeCommercialListings }}</div>
                @if(auth()->user()?->isAdmin())
                    <a href="{{ route('admin.marketplace.listings.index') }}" style="font-size:13px;font-weight:600">Active pet sale listings →</a>
                @else
                    <span style="font-size:13px;font-weight:600;color:#5B6460">Active pet sale listings</span>
                @endif
            </div>

            {{-- 2. Buy Requests --}}
            <div class="card" style="padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">BUY REQUESTS</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#C2620C"></span>
                </div>
                <div style="font-size:34px;font-weight:700;margin:10px 0 4px;color:#12392A">{{ $pendingPurchaseRequests }}</div>
                <a href="{{ route('admin.orders.index') }}" style="font-size:13px;font-weight:600;color:#9A4A06">Pending customer orders →</a>
            </div>

            {{-- 3. Payment Review --}}
            <div class="card" style="padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">PAYMENT REVIEW</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#1D6FA5"></span>
                </div>
                <div style="font-size:34px;font-weight:700;margin:10px 0 4px;color:#12392A">{{ $paymentsRequiringReview }}</div>
                <a href="{{ route('admin.orders.index') }}" style="font-size:13px;font-weight:600;color:#14598A">Pending COD / payments →</a>
            </div>

            {{-- 4. Adoption Posts --}}
            <div class="card" style="padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:12px;letter-spacing:.08em;color:#5B6460;font-weight:600">ADOPTION POSTS</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:#BE2A57"></span>
                </div>
                <div style="font-size:34px;font-weight:700;margin:10px 0 4px;color:#12392A">{{ $publishedAdoptionPosts }}</div>
                <a href="{{ route('admin.community.index') }}" style="font-size:13px;font-weight:600;color:#A31F48">Published shelter &amp; rescues →</a>
            </div>
        </section>

        {{-- Row 2: Recent Store Orders & Recent Moderation Activity --}}
        <section class="row2">
            {{-- Recent store orders --}}
            <div class="card" style="padding:24px">
                <div style="display:flex;justify-content:space-between;align-items:baseline">
                    <h2 style="margin:0;font-size:17px;font-weight:700;color:#101814">Recent store orders</h2>
                    <a href="{{ route('admin.orders.index') }}" style="font-size:13px;font-weight:600">View all ({{ $totalOrders }}) →</a>
                </div>
                <p style="margin:2px 0 16px;font-size:13px;color:#5B6460">Latest purchases from the PawHaven Pet Shop.</p>

                @if($recentOrders->isEmpty())
                    <div style="border:1.5px dashed #D9D4C7;border-radius:12px;padding:28px;text-align:center">
                        <div style="font-weight:600;font-size:14px">No orders placed yet</div>
                        <div style="font-size:13px;color:#5B6460;margin:4px 0 12px">Add products to start taking orders.</div>
                        @can('create', \App\Models\Product::class)
                            <a class="btn" href="{{ route('admin.products.create') }}" style="background:#17603F;color:#fff">+ Add Product</a>
                        @endcan
                    </div>
                @else
                    <div style="display:flex;flex-direction:column">
                        @foreach($recentOrders as $order)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #EAEFEA">
                                <div>
                                    <a href="{{ route('admin.orders.show', $order) }}" style="font-weight:600;font-size:13.5px;color:#17603F">
                                        #{{ $order->order_number }}
                                    </a>
                                    <div style="color:#5B6460;font-size:12px;margin-top:2px">
                                        {{ $order->customer_name }} &bull; {{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent moderation activity --}}
            <div class="card" style="padding:24px">
                <div style="display:flex;justify-content:space-between;align-items:baseline">
                    <h2 style="margin:0;font-size:17px;font-weight:700;color:#101814">Recent moderation activity</h2>
                    @if(auth()->user()?->isAdmin())
                        <a href="{{ route('admin.marketplace.moderation.index') }}" style="font-size:13px;font-weight:600">Queue →</a>
                    @endif
                </div>
                <p style="margin:2px 0 16px;font-size:13px;color:#5B6460">Pet listings reviewed by administration.</p>

                @if($recentModerations->isEmpty())
                    <div style="border:1.5px dashed #D9D4C7;border-radius:12px;padding:28px;text-align:center">
                        <div style="font-weight:600;font-size:14px">No moderation actions yet</div>
                        <div style="font-size:13px;color:#5B6460;margin:4px 0 12px">Reviewed listings will appear here.</div>
                        @if(auth()->user()?->isAdmin())
                            <a class="btn" href="{{ route('admin.marketplace.moderation.index') }}" style="background:#fff;border:1px solid #E6E2D8;color:#1B2420">Open Moderation Queue</a>
                        @endif
                    </div>
                @else
                    <div style="display:flex;flex-direction:column">
                        @foreach($recentModerations as $mod)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #EAEFEA">
                                <div>
                                    <div style="font-weight:600;font-size:13.5px;color:#1B2420;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        {{ $mod->petListing->title ?? 'Listing #'.$mod->pet_listing_id }}
                                    </div>
                                    <div style="color:#5B6460;font-size:12px;margin-top:2px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        {{ $mod->note ?: 'No note provided' }}
                                    </div>
                                </div>
                                <div style="text-align:right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $mod->action === \App\Enums\ListingModerationActionType::Approved ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                        {{ ucfirst($mod->action->value) }}
                                    </span>
                                    <div style="font-size:10.5px;color:#5B6460;margin-top:4px">{{ $mod->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- System Audit Trail Section (Admin or Branch Operations) --}}
        @if(auth()->user()?->isAdmin() || ($navCanBranchOperations ?? false))
            <section class="card" style="padding:24px">
                <div style="display:flex;justify-content:space-between;align-items:baseline">
                    <h2 style="margin:0;font-size:17px;font-weight:700;color:#101814">System audit trail</h2>
                    <a href="{{ route('admin.audit-logs.index') }}" style="font-size:13px;font-weight:600">View full trail →</a>
                </div>
                <p style="margin:2px 0 12px;font-size:13px;color:#5B6460">Permanent record of administrative changes and governance events.</p>

                @if($recentAuditLogs->isEmpty())
                    <div style="border:1.5px dashed #D9D4C7;border-radius:12px;padding:20px;text-align:center;font-size:13px;color:#5B6460">
                        No administrative audit logs recorded yet.
                    </div>
                @else
                    <div style="display:flex;flex-direction:column">
                        @foreach($recentAuditLogs as $log)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #EAEFEA;font-size:13px">
                                <div style="display:flex;align-items:center;gap:10px">
                                    <span style="font-family:monospace;font-size:11px;font-weight:700;color:#17603F;padding:2px 6px;border-radius:4px;background:#EEF5F0;border:1px solid #C9D8CF">
                                        {{ $log->action }}
                                    </span>
                                    <span style="color:#1B2420">{{ $log->description }}</span>
                                </div>
                                <div style="font-size:11px;color:#5B6460;text-align:right;flex-shrink:0">
                                    {{ $log->user->name ?? 'System' }} &bull; {{ $log->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </main>
</x-layouts::admin>
