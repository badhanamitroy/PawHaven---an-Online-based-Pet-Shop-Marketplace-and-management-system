@php
    $navUser = auth()->user();
    $navMemberships = $navUser?->isStaff()
        ? app(\App\Services\BusinessAccessService::class)->activeMemberships($navUser)
        : collect();
    $navIsShopOwner = $navMemberships->contains(fn ($membership) => $membership->role === 'owner');
    $navIsEmployeeSeller = $navMemberships->contains(fn ($membership) => $membership->role === 'seller');
    $navIsBranchManager = $navMemberships->contains(fn ($membership) => $membership->role === 'manager');
    $navCanBranchOperations = $navMemberships->contains(fn ($membership) => in_array($membership->role, ['owner', 'manager'], true));

    $isWebActive = request()->routeIs('admin.homepage.*', 'admin.brand.*');
    $isBizActive = request()->routeIs(
        'admin.marketplace.*',
        'admin.sales-desk.*',
        'admin.sales-reports.*',
        'admin.orders.*',
        'admin.fulfilment.*',
        'admin.business.*',
        'admin.customers.*',
        'admin.products.*',
        'admin.pricing.*',
        'admin.categories.*',
        'admin.inventory.*',
        'admin.breeds.*',
        'admin.community.*',
        'admin.settings.shop*'
    );
    $isPlatActive = request()->routeIs('admin.settings.features', 'admin.system.*', 'admin.users.*');
    $isCarActive = request()->routeIs('admin.careers.*', 'admin.invitations.*', 'admin.audit-logs.*');

    $pendingModerationCount = \App\Models\PetListing::where('status', \App\Enums\PetListingStatus::PendingReview)->count();
@endphp

<a class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}" href="{{ route('admin.dashboard') }}" aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}">
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="14" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
    </svg>
    <span>Dashboard</span>
</a>

<div class="grp">Manage</div>

{{-- 1. Website Group --}}
@if(auth()->user()?->isAdmin())
<div>
    <button class="grp-btn" data-group="web" aria-controls="sub-web" aria-expanded="{{ $isWebActive ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
        </svg>
        <span>Website</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="chev">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>
    <div class="sub" id="sub-web" {{ $isWebActive ? '' : 'hidden' }}>
        <a class="{{ request()->routeIs('admin.homepage.*') ? 'on' : '' }}" href="{{ route('admin.homepage.index') }}">
            <span>Website Manager</span>
            @if(\App\Facades\Settings::get('homepage.draft_exists'))
                <span style="margin-left:auto;background:#F59E0B;color:#000;font-size:9px;font-weight:800;padding:1px 5px;border-radius:10px;text-transform:uppercase">Draft</span>
            @endif
        </a>
        <a class="{{ request()->routeIs('admin.brand.*') ? 'on' : '' }}" href="{{ route('admin.brand.index') }}">
            <span>Brand &amp; Logos</span>
        </a>
    </div>
</div>
@endif

{{-- 2. Business Group --}}
<div>
    <button class="grp-btn" data-group="biz" aria-controls="sub-biz" aria-expanded="{{ $isBizActive ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m3 9 1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/>
        </svg>
        <span>Business</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="chev">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>
    <div class="sub" id="sub-biz" {{ $isBizActive ? '' : 'hidden' }}>
        @if(auth()->user()?->isAdmin())
            <a class="{{ request()->routeIs('admin.marketplace.listings.*') ? 'on' : '' }}" href="{{ route('admin.marketplace.listings.index') }}">
                <span>Marketplace Listings</span>
            </a>
            <a class="{{ request()->routeIs('admin.marketplace.moderation.*') ? 'on' : '' }}" href="{{ route('admin.marketplace.moderation.index') }}">
                <span>Moderation Queue</span>
                @if($pendingModerationCount > 0)
                    <span style="margin-left:auto;background:#F28C75;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px">{{ $pendingModerationCount }}</span>
                @endif
            </a>
        @endif

        @can('viewAny', \App\Models\BusinessMembership::class)
            <a class="{{ request()->routeIs('admin.business.*') ? 'on' : '' }}" href="{{ route('admin.business.index') }}">
                <span>Companies &amp; Branch Access</span>
            </a>
        @endcan

        @if($navIsEmployeeSeller)
            <a class="{{ request()->routeIs('admin.sales-desk.*') ? 'on' : '' }}" href="{{ route('admin.sales-desk.index') }}">
                <span>Employee Sales Desk</span>
            </a>
            <a class="{{ request()->routeIs('admin.sales-reports.my*') ? 'on' : '' }}" href="{{ route('admin.sales-reports.my') }}">
                <span>My Sales</span>
            </a>
        @endif

        @if($navIsBranchManager)
            <a class="{{ request()->routeIs('admin.sales-reports.branch*') ? 'on' : '' }}" href="{{ route('admin.sales-reports.branch') }}">
                <span>Branch Sales</span>
            </a>
        @endif

        @if($navIsShopOwner)
            <a class="{{ request()->routeIs('admin.sales-reports.business*') ? 'on' : '' }}" href="{{ route('admin.sales-reports.business') }}">
                <span>Business Sales</span>
            </a>
        @endif

        @if($navCanBranchOperations)
            <a class="{{ request()->routeIs('admin.orders.*') ? 'on' : '' }}" href="{{ route('admin.orders.index') }}">
                <span>Customer Orders</span>
            </a>
            <a class="{{ request()->routeIs('admin.fulfilment.*') ? 'on' : '' }}" href="{{ route('admin.fulfilment.index') }}">
                <span>Order Fulfilment</span>
            </a>
            <a class="{{ request()->routeIs('admin.customers.*') ? 'on' : '' }}" href="{{ route('admin.customers.index') }}">
                <span>Customer Directory</span>
            </a>
        @endif

        @can('viewAny', \App\Models\Product::class)
            <a class="{{ request()->routeIs('admin.products.*') ? 'on' : '' }}" href="{{ route('admin.products.index') }}">
                <span>Products Catalog</span>
            </a>
        @endcan

        @if($navIsShopOwner)
            <a class="{{ request()->routeIs('admin.pricing.*') ? 'on' : '' }}" href="{{ route('admin.pricing.index') }}">
                <span>Pricing &amp; Promotions</span>
            </a>
        @endif

        @can('viewAny', \App\Models\ProductCategory::class)
            <a class="{{ request()->routeIs('admin.categories.*') ? 'on' : '' }}" href="{{ route('admin.categories.index') }}">
                <span>Product Categories</span>
            </a>
        @endcan

        @can('viewAny', \App\Models\Product::class)
            <a class="{{ request()->routeIs('admin.inventory.*') ? 'on' : '' }}" href="{{ route('admin.inventory.index') }}">
                <span>Inventory &amp; Stock</span>
            </a>
        @endcan

        @if(auth()->user()?->isAdmin())
            <a class="{{ request()->routeIs('admin.marketplace.categories.*') ? 'on' : '' }}" href="{{ route('admin.marketplace.categories.index') }}">
                <span>Species Categories</span>
            </a>
        @endif

        <a class="{{ request()->routeIs('admin.breeds.*') ? 'on' : '' }}" href="{{ route('admin.breeds.index') }}">
            <span>Breed Profiles</span>
        </a>

        <a class="{{ request()->routeIs('admin.community.*') ? 'on' : '' }}" href="{{ route('admin.community.index') }}">
            <span>Adoption &amp; Community Hub</span>
        </a>

        @if($navIsShopOwner)
            <a class="{{ request()->routeIs('admin.settings.shop*') ? 'on' : '' }}" href="{{ route('admin.settings.shop') }}">
                <span>Shop &amp; Delivery Fees</span>
            </a>
        @endif
    </div>
</div>

{{-- 3. Platform Group --}}
@if(auth()->user()?->isAdmin())
<div>
    <button class="grp-btn" data-group="plat" aria-controls="sub-plat" aria-expanded="{{ $isPlatActive ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m12 3 8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4"/>
        </svg>
        <span>Platform</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="chev">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>
    <div class="sub" id="sub-plat" {{ $isPlatActive ? '' : 'hidden' }}>
        <a class="{{ request()->routeIs('admin.settings.features*') ? 'on' : '' }}" href="{{ route('admin.settings.features') }}">
            <span>Feature Flags</span>
        </a>
        <a class="{{ request()->routeIs('admin.system.*') ? 'on' : '' }}" href="{{ route('admin.system.index') }}">
            <span>System &amp; Security Health</span>
        </a>
        <a class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}" href="{{ route('admin.users.index') }}">
            <span>Users &amp; Staff Roles</span>
        </a>
    </div>
</div>
@endif

{{-- 4. Careers & access Group --}}
@if(auth()->user()?->isAdmin() || $navIsShopOwner || $navCanBranchOperations)
<div>
    <button class="grp-btn" data-group="car" aria-controls="sub-car" aria-expanded="{{ $isCarActive ? 'true' : 'false' }}">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 7h18v13H3zM8 7V4h8v3"/>
        </svg>
        <span>Careers &amp; access</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="chev">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>
    <div class="sub" id="sub-car" {{ $isCarActive ? '' : 'hidden' }}>
        @if(auth()->user()?->isAdmin())
            <a class="{{ request()->routeIs('admin.careers.vacancies.*', 'admin.careers.applications.*') ? 'on' : '' }}" href="{{ route('admin.careers.vacancies.index') }}">
                <span>Careers recruitment</span>
            </a>
        @endif

        @if($navIsShopOwner)
            <a class="{{ request()->routeIs('admin.careers.approvals.*') ? 'on' : '' }}" href="{{ route('admin.careers.approvals.index') }}">
                <span>Employee approvals</span>
            </a>
        @endif

        @if(auth()->user()?->isAdmin() || $navIsShopOwner)
            <a class="{{ request()->routeIs('admin.invitations.*') ? 'on' : '' }}" href="{{ route('admin.invitations.index') }}">
                <span>Exceptional invitations</span>
            </a>
        @endif

        @if(auth()->user()?->isAdmin() || $navCanBranchOperations)
            <a class="{{ request()->routeIs('admin.audit-logs.*') ? 'on' : '' }}" href="{{ route('admin.audit-logs.index') }}">
                <span>Activity &amp; Audit Trail</span>
            </a>
        @endif
    </div>
</div>
@endif
