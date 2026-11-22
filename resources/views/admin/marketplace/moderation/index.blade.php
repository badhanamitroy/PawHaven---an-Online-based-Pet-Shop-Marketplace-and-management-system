@extends('layouts.admin')

@section('title', 'Marketplace Moderation Queue')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Business / Moderation Queue</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Moderation queue</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Review lawful domestic pet listings submitted by community members before public publication.</div>
        </div>
        <span class="pend {{ $pendingListings->total() === 0 ? 'zero' : '' }}" id="pend" role="status">
            {{ $pendingListings->total() }} pending review
        </span>
        <a class="btn" href="{{ route('admin.marketplace.listings.index') }}" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
            All listings
        </a>
    </header>

    {{-- Stats Row --}}
    <section class="stats" aria-label="Moderation summary">
        <div class="card stat">
            <div class="k">Pending review</div>
            <div class="v" id="sp">{{ $pendingListings->total() }}</div>
        </div>
        <div class="card stat">
            <div class="k">Reviewed today</div>
            <div class="v" id="sa">
                {{ \App\Models\PetListing::whereDate('updated_at', today())->whereIn('status', [\App\Enums\PetListingStatus::Published, \App\Enums\PetListingStatus::Rejected])->count() }}
            </div>
        </div>
        <div class="card stat">
            <div class="k">Total published</div>
            <div class="v" id="sr" style="color:#17603F">
                {{ \App\Models\PetListing::where('status', \App\Enums\PetListingStatus::Published)->count() }}
            </div>
        </div>
    </section>

    @if($pendingListings->isEmpty())
        {{-- Queue is Clear State --}}
        <section class="card" id="clear" style="padding:56px 24px;text-align:center">
            <div style="width:52px;height:52px;border-radius:50%;background:#E3F3E9;margin:0 auto 14px;display:flex;align-items:center;justify-content:center">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#17603F" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 12 5 5 9-10"/>
                </svg>
            </div>
            <div style="font-weight:600;font-size:16px;color:#101814">Queue is clear</div>
            <div style="font-size:13px;color:#5B6460;margin:4px 0 16px">All submitted pet listings have been reviewed and moderated.</div>
            <a class="btn" href="{{ route('admin.marketplace.listings.index') }}" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
                View all listings →
            </a>
        </section>
    @else
        {{-- Moderation Cards List --}}
        <div id="list" style="display:flex;flex-direction:column;gap:16px">
            @foreach($pendingListings as $listing)
                <article class="card q" id="listing-card-{{ $listing->id }}">
                    {{-- Left Column: Details & Images --}}
                    <div>
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                            <div>
                                <h2 style="margin:0;font-size:17px;font-weight:700;color:#101814">
                                    <a href="{{ route('admin.marketplace.listings.show', $listing) }}" style="color:#101814;text-decoration:none">
                                        {{ $listing->title }}
                                    </a>
                                </h2>
                            </div>
                            <div style="font-weight:700;font-size:17px;white-space:nowrap;color:#101814">
                                @if($listing->price_minor)
                                    {{ $listing->currency }} {{ number_format($listing->price_minor / 100, 2) }}
                                @else
                                    <span style="color:#14573A">Free / Adoption</span>
                                @endif
                            </div>
                        </div>

                        <div class="meta">
                            <span><strong>Category:</strong> {{ $listing->petCategory?->name ?? 'General' }}</span>
                            <span><strong>Breed:</strong> {{ $listing->breed?->name ?? 'Mixed' }}</span>
                            <span><strong>Seller:</strong> {{ $listing->seller?->name }} ({{ $listing->seller?->email }})</span>
                            <span><strong>Location:</strong> {{ $listing->location }}</span>
                            <span><strong>Submitted:</strong> {{ $listing->created_at?->format('d M Y') }}</span>
                        </div>

                        @if($listing->images->isNotEmpty())
                            <div style="display:flex;gap:10px;margin-top:14px;overflow-x:auto;padding-bottom:4px">
                                @foreach($listing->images as $img)
                                    <img src="{{ $img->url() }}" alt="{{ $listing->title }}" style="width:80px;height:80px;border-radius:8px;object-fit:cover;border:1px solid #E3E7E4;flex-shrink:0" />
                                @endforeach
                            </div>
                        @endif

                        <p style="font-size:14px;line-height:1.55;color:#3B4540;margin:14px 0 0">
                            {{ $listing->description }}
                        </p>
                    </div>

                    {{-- Right Column: Checklist & Action Form --}}
                    <div>
                        <div class="lbl" style="letter-spacing:.06em;font-size:11px;text-transform:uppercase;color:#5B6460;margin-bottom:8px">
                            Review checklist
                        </div>

                        <div style="display:flex;flex-direction:column;gap:4px">
                            <label class="chk">
                                <input type="checkbox" onchange="validateChecklist({{ $listing->id }})" class="chk-item-{{ $listing->id }}">
                                <span>Lawful domestic pet species</span>
                            </label>
                            <label class="chk">
                                <input type="checkbox" onchange="validateChecklist({{ $listing->id }})" class="chk-item-{{ $listing->id }}">
                                <span>Photos match the description</span>
                            </label>
                            <label class="chk">
                                <input type="checkbox" onchange="validateChecklist({{ $listing->id }})" class="chk-item-{{ $listing->id }}">
                                <span>Seller details look genuine</span>
                            </label>
                        </div>

                        <div style="display:flex;gap:8px;margin-top:14px">
                            <form method="POST" action="{{ route('admin.marketplace.moderation.approve', $listing) }}" style="flex:1">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn"
                                    id="ap-btn-{{ $listing->id }}"
                                    style="width:100%;justify-content:center;background:#17603F;color:#fff;border:0;cursor:pointer"
                                >
                                    Approve
                                </button>
                            </form>
                            <button
                                type="button"
                                class="btn"
                                onclick="toggleRejectBox({{ $listing->id }})"
                                style="flex:1;justify-content:center;background:#fff;border:1px solid #D3D9D5;color:#9B1C12;cursor:pointer"
                            >
                                Reject
                            </button>
                        </div>

                        {{-- Reject Details Box --}}
                        <div id="reject-box-{{ $listing->id }}" style="display:none;margin-top:12px;padding:12px;background:#FDF2F2;border:1px solid #F87171/30;border-radius:8px">
                            <form method="POST" action="{{ route('admin.marketplace.moderation.reject', $listing) }}">
                                @csrf
                                <label for="note-{{ $listing->id }}" class="lbl" style="color:#991B1B">Rejection reason *</label>
                                <select
                                    class="inp sel"
                                    style="margin-bottom:8px;font-size:13px"
                                    onchange="handleReasonSelect({{ $listing->id }}, this.value)"
                                >
                                    <option value="">Select a standard reason</option>
                                    <option value="Unlawful or restricted species">Unlawful or restricted species</option>
                                    <option value="Misleading photos or details">Misleading photos or details</option>
                                    <option value="Seller could not be verified">Seller could not be verified</option>
                                    <option value="Duplicate listing">Duplicate listing</option>
                                    <option value="custom">Other / Custom reason</option>
                                </select>
                                <textarea
                                    name="note"
                                    id="note-{{ $listing->id }}"
                                    class="inp"
                                    style="min-height:72px;font-size:13px"
                                    placeholder="Specify rejection reason for the seller..."
                                    required
                                ></textarea>
                                <button
                                    type="submit"
                                    class="btn"
                                    style="width:100%;justify-content:center;background:#B42318;color:#fff;border:0;cursor:pointer;margin-top:8px"
                                >
                                    Confirm rejection
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach

            @if($pendingListings->hasPages())
                <div style="padding:16px 0">
                    {{ $pendingListings->links() }}
                </div>
            @endif
        </div>
    @endif
</main>

<style>
.stat { padding:18px 20px; }
.stat .k { font-size:12px; letter-spacing:.08em; color:#5B6460; font-weight:600; text-transform:uppercase; }
.stat .v { font-size:30px; font-weight:700; margin-top:6px; color:#101814; }
.q { padding:20px; display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:24px; }
@media(max-width:900px){ .q { grid-template-columns:1fr; } }
.meta { display:flex; flex-wrap:wrap; gap:6px 18px; font-size:13px; color:#5B6460; margin-top:6px; }
.chk { display:flex; align-items:center; gap:10px; min-height:34px; font-size:13px; cursor:pointer; color:#1B2420; }
.chk input { width:17px; height:17px; accent-color:#17603F; flex:none; }
.pend { background:#FFF1DC; color:#8A4B06; padding:5px 12px; border-radius:999px; font-size:13px; font-weight:600; }
.pend.zero { background:#E3F3E9; color:#14573A; }
.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
@media(max-width:760px){ .stats { grid-template-columns:1fr; } }
</style>

<script>
function toggleRejectBox(id) {
    var box = document.getElementById('reject-box-' + id);
    if (box) {
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }
}

function handleReasonSelect(id, value) {
    var ta = document.getElementById('note-' + id);
    if (!ta) return;
    if (value && value !== 'custom') {
        ta.value = value;
    } else if (value === 'custom') {
        ta.value = '';
        ta.focus();
    }
}

function validateChecklist(id) {
    var boxes = document.querySelectorAll('.chk-item-' + id);
    var btn = document.getElementById('ap-btn-' + id);
    if (!btn || !boxes.length) return;
    var allChecked = true;
    boxes.forEach(function(b) {
        if (!b.checked) allChecked = false;
    });
    // Visual cue: slight highlight when verified
    if (allChecked) {
        btn.style.boxShadow = '0 0 0 2px rgba(23,96,63,0.3)';
    } else {
        btn.style.boxShadow = '';
    }
}
</script>
@endsection
