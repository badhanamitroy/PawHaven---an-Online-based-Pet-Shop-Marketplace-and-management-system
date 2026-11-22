@extends('layouts.admin')

@section('title', 'Marketplace Pet Listings')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Business / Marketplace Listings</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Marketplace pet listings</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Review community listings, check status, verify seller submissions and inspect details.</div>
        </div>
        <a class="btn" href="{{ route('admin.marketplace.moderation.index') }}" style="background:#17603F;color:#fff">
            Moderation queue
        </a>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
            View public store ↗
        </a>
    </header>

    {{-- Filter Bar --}}
    <form class="card fbar" id="f" method="GET" action="{{ route('admin.marketplace.listings.index') }}" role="search">
        <div class="f">
            <label for="q">Search</label>
            <input class="inp" id="q" name="search" type="search" value="{{ request('search') }}" placeholder="Title, location or seller">
        </div>
        <div class="f">
            <label for="cat">Category</label>
            <select class="inp sel" id="cat" name="category">
                <option value="">All pet categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="f">
            <label for="stt">Status</label>
            <select class="inp sel" id="stt" name="status">
                <option value="">All statuses</option>
                @foreach(\App\Enums\PetListingStatus::cases() as $st)
                    <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $st->value)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;cursor:pointer">
                Filter
            </button>
            @if(request()->hasAny(['search', 'category', 'status']))
                <a class="btn" href="{{ route('admin.marketplace.listings.index') }}" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
                    Reset
                </a>
            @endif
        </div>
    </form>

    {{-- Listings Table Card --}}
    <section class="card" style="overflow:hidden">
        <div style="display:flex;align-items:center;padding:16px 20px;border-bottom:1px solid #E3E7E4">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814;flex:1">
                Listings
                <span id="cnt" style="font-weight:500;color:#5B6460;font-size:13px;margin-left:6px">
                    {{ $listings->total() }} {{ $listings->total() === 1 ? 'result' : 'results' }}
                </span>
            </h2>
        </div>

        @if($listings->isEmpty())
            <div id="empty" style="padding:56px 24px;text-align:center">
                <div style="font-weight:600;font-size:15px;color:#101814">
                    {{ request()->hasAny(['search', 'category', 'status']) ? 'No pet listings match the selected filters' : 'No pet listings yet' }}
                </div>
                <div style="font-size:13px;color:#5B6460;margin-top:4px">
                    {{ request()->hasAny(['search', 'category', 'status']) ? 'Try a different search, or reset the filters.' : 'New community listings will appear here for review.' }}
                </div>
            </div>
        @else
            <div style="overflow-x:auto">
                <table id="tb" style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Listing</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Category</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Seller</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Location</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Price</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Status</th>
                            <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Submitted</th>
                            <th style="text-align:right;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4"><span style="position:absolute;left:-9999px">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @foreach($listings as $item)
                            <tr>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF">
                                    <div style="display:flex;align-items:center;gap:12px">
                                        <div class="th">
                                            @if($item->images->isNotEmpty())
                                                <img src="{{ $item->images->first()->url() }}" alt="{{ $item->title }}" />
                                            @else
                                                {{ strtoupper(substr($item->title, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div style="font-weight:600;max-width:260px;color:#1B2420">
                                            <a href="{{ route('admin.marketplace.listings.show', $item) }}" style="color:#1B2420;text-decoration:none">
                                                {{ $item->title }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#1B2420">
                                    <div>{{ $item->petCategory?->name ?? 'General' }}</div>
                                    <div style="font-size:12px;color:#5B6460">{{ $item->breed?->name ?? 'Mixed/Other' }}</div>
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#1B2420">
                                    <div>{{ $item->seller?->name ?? 'Unknown' }}</div>
                                    <div style="font-size:12px;color:#5B6460">{{ $item->seller?->email }}</div>
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#5B6460">
                                    {{ $item->location }}
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;font-weight:600;color:#101814">
                                    @if($item->price_minor)
                                        {{ $item->currency }} {{ number_format($item->price_minor / 100, 2) }}
                                    @else
                                        <span style="color:#14573A">Free / Rehome</span>
                                    @endif
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF">
                                    @php
                                        $statusSlug = match($item->status) {
                                            \App\Enums\PetListingStatus::Published => 'approved',
                                            \App\Enums\PetListingStatus::PendingReview => 'pending',
                                            \App\Enums\PetListingStatus::Rejected => 'rejected',
                                            \App\Enums\PetListingStatus::Completed => 'sold',
                                            default => 'pending',
                                        };
                                        $statusLabel = match($item->status) {
                                            \App\Enums\PetListingStatus::Published => 'Approved',
                                            \App\Enums\PetListingStatus::PendingReview => 'Pending review',
                                            \App\Enums\PetListingStatus::Rejected => 'Rejected',
                                            \App\Enums\PetListingStatus::Completed => 'Sold',
                                            default => ucfirst(str_replace('_', ' ', $item->status->value)),
                                        };
                                    @endphp
                                    <span class="s {{ $statusSlug }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#5B6460;font-size:13px">
                                    {{ $item->created_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;text-align:right">
                                    <a href="{{ route('admin.marketplace.listings.show', $item) }}" class="lk" aria-label="Review {{ $item->title }}">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #E3E7E4;font-size:13px;color:#5B6460;flex-wrap:wrap;gap:12px">
                <span id="pg">
                    Showing {{ $listings->firstItem() ?? 0 }} to {{ $listings->lastItem() ?? 0 }} of {{ $listings->total() }} results
                </span>
                @if($listings->hasPages())
                    <div>
                        {{ $listings->links() }}
                    </div>
                @endif
            </div>
        @endif
    </section>
</main>

<style>
.th { width:44px; height:44px; border-radius:8px; background:#E3F3E9; color:#14573A; display:flex; align-items:center; justify-content:center; font-weight:700; flex:none; overflow:hidden; }
.th img { width:100%; height:100%; object-fit:cover; }
.s { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
.s.pending { background:#FFF1DC; color:#8A4B06; }
.s.approved { background:#E3F3E9; color:#14573A; }
.s.rejected { background:#FDECEA; color:#9B1C12; }
.s.sold { background:#ECEEED; color:#46504A; }
.fbar { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1fr) auto; gap:12px; align-items:end; padding:18px 20px; }
@media(max-width:1000px){ .fbar { grid-template-columns:1fr 1fr; } }
@media(max-width:600px){ .fbar { grid-template-columns:1fr; } }
.lk { color:#17603F; font-weight:600; font-size:13px; text-decoration:none; }
.lk:hover { text-decoration:underline; }
</style>
@endsection
