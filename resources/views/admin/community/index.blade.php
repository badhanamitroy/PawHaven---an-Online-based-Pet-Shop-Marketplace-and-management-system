@extends('layouts.admin')

@section('title', 'Adoption & Rescue Hub')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Business / Adoption &amp; Community Hub</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Adoption &amp; rescue community hub</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Configure emergency rescue contacts and adoption guidelines, and monitor community animal welfare rehoming.</div>
        </div>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
            View public store ↗
        </a>
    </header>

    {{-- Stats Row --}}
    <section class="stats" aria-label="Hub summary">
        <div class="card stat">
            <div class="k">Active adoption listings</div>
            <div class="v" id="s1">{{ $adoptionCount }}</div>
            <div class="d">Seeking loving forever homes</div>
        </div>
        <div class="card stat">
            <div class="k">Urgent rehome cases</div>
            <div class="v {{ $rehomeCount > 0 ? 'warn' : '' }}" id="s2">{{ $rehomeCount }}</div>
            <div class="d">Rehoming support cases</div>
        </div>
        <div class="card stat">
            <div class="k">Partner shelters</div>
            <div class="v" id="s3">{{ $settings['community.rescue_partners_count'] ?? '12' }}</div>
            <div class="d">Verified partner rescue groups</div>
        </div>
    </section>

    <div class="cols2">
        {{-- Left Column: Rescue & Support Settings Form --}}
        <section class="card" style="padding:24px">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Rescue &amp; support settings</h2>
            <p style="margin:2px 0 18px;font-size:13px;color:#5B6460">Shown on public adoption and rehoming pages.</p>

            <form method="POST" action="{{ route('admin.community.update') }}" id="f" novalidate>
                @csrf
                @method('PUT')

                <div class="f">
                    <label for="em">Rescue support email <span style="color:#B42318" aria-hidden="true">*</span></label>
                    <input
                        class="inp"
                        id="em"
                        type="email"
                        name="rescue_email"
                        value="{{ old('rescue_email', $settings['community.rescue_email'] ?? 'rescue@pawhaven.com') }}"
                        required
                    >
                    @error('rescue_email')
                        <div class="err" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="hl">Emergency rescue hotline <span style="color:#B42318" aria-hidden="true">*</span></label>
                    <input
                        class="inp"
                        id="hl"
                        type="tel"
                        name="rescue_hotline"
                        value="{{ old('rescue_hotline', $settings['community.rescue_hotline'] ?? '+880 1800-RESCUE') }}"
                        placeholder="+880 1XXX-XXXXXX"
                        required
                    >
                    @error('rescue_hotline')
                        <div class="err" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="pc">Partner shelters count</label>
                    <input
                        class="inp"
                        id="pc"
                        type="number"
                        min="0"
                        name="rescue_partners_count"
                        value="{{ old('rescue_partners_count', $settings['community.rescue_partners_count'] ?? '12') }}"
                    >
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="gd">Adoption guidelines &amp; code of ethics</label>
                    <textarea
                        class="inp"
                        id="gd"
                        name="adoption_guidelines"
                        style="min-height:140px"
                    >{{ old('adoption_guidelines', $settings['community.adoption_guidelines'] ?? 'Every adoption applicant must undergo identity verification and household readiness assessment. PawHaven prohibits unlawful breeding and promotes responsible lifelong pet guardianship.') }}</textarea>
                    <div class="hint"><span id="cn">0</span> characters</div>
                </div>

                <div style="display:flex;align-items:center;gap:12px;margin-top:14px">
                    <button class="btn" type="submit" style="flex:1;justify-content:center;background:#17603F;color:#fff;border:0;cursor:pointer">
                        Save settings
                    </button>
                    <span id="dirty" style="font-size:13px;color:#5B6460"></span>
                </div>
            </form>
        </section>

        {{-- Right Column: Recent Community Adoption Cases --}}
        <section class="card" style="overflow:hidden">
            <div style="display:flex;align-items:center;padding:16px 20px;border-bottom:1px solid #E3E7E4">
                <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814;flex:1">Recent community adoption cases</h2>
                <a href="{{ route('admin.marketplace.listings.index') }}" style="font-size:13px;font-weight:600;color:#17603F;text-decoration:none">
                    View all →
                </a>
            </div>

            @if($recentAdoptions->isEmpty())
                <div id="empty" style="padding:56px 24px;text-align:center">
                    <div style="font-weight:600;font-size:15px;color:#101814">No active cases</div>
                    <div style="font-size:13px;color:#5B6460;margin-top:4px">No active adoption or rehoming cases in the database.</div>
                </div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:14px">
                        <thead>
                            <tr>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Pet</th>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Type</th>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Location</th>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Date</th>
                                <th style="text-align:right;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4"><span style="position:absolute;left:-9999px">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAdoptions as $item)
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;font-weight:600;color:#1B2420">
                                        <a href="{{ route('admin.marketplace.listings.show', $item) }}" style="color:#1B2420;text-decoration:none">
                                            {{ $item->title }}
                                        </a>
                                        <div style="font-size:12px;font-weight:normal;color:#5B6460;margin-top:2px">
                                            {{ $item->petCategory?->name }} · {{ $item->breed?->name }}
                                        </div>
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF">
                                        @php
                                            $isUrgent = str_contains(strtolower($item->listing_type->value ?? ''), 'rehome') || str_contains(strtolower($item->title), 'urgent');
                                        @endphp
                                        <span class="s {{ $isUrgent ? 'urgent' : 'adopt' }}">
                                            {{ $isUrgent ? 'Urgent rehome' : 'Adoption' }}
                                        </span>
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#5B6460;font-size:13px">
                                        {{ $item->location }}
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;color:#5B6460;font-size:13px">
                                        {{ $item->created_at?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;text-align:right">
                                        <a href="{{ route('admin.marketplace.listings.show', $item) }}" style="color:#17603F;font-weight:600;font-size:13px;text-decoration:none">
                                            Inspect
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</main>

<style>
.stat { padding:18px 20px; }
.stat .k { font-size:12px; letter-spacing:.08em; color:#5B6460; font-weight:600; text-transform:uppercase; }
.stat .v { font-size:34px; font-weight:700; margin:8px 0 2px; color:#101814; }
.stat .v.warn { color:#B42318; }
.stat .d { font-size:13px; color:#5B6460; }
.cols2 { display:grid; grid-template-columns:420px minmax(0,1fr); gap:20px; align-items:start; }
@media(max-width:1000px){ .cols2 { grid-template-columns:1fr; } }
.s { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
.s.adopt { background:#E3F3E9; color:#14573A; }
.s.urgent { background:#FDECEA; color:#9B1C12; }
.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
@media(max-width:760px){ .stats { grid-template-columns:1fr; } }
</style>

<script>
function updateCharCount() {
    var gd = document.getElementById('gd');
    var cn = document.getElementById('cn');
    if (gd && cn) {
        cn.textContent = gd.value.length;
    }
}

document.getElementById('gd')?.addEventListener('input', updateCharCount);
updateCharCount();

document.querySelectorAll('#f input, #f textarea').forEach(function(el) {
    el.addEventListener('input', function() {
        var d = document.getElementById('dirty');
        if (d) d.textContent = 'Unsaved changes';
    });
});
</script>
@endsection
