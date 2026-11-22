@extends('layouts.admin')

@section('title', 'Homepage Manager')

@section('content')
@php
    $isDraft = !empty($settings['homepage.draft_exists']);
    $val = fn($key, $default = '') => old($key, $isDraft ? ($settings['homepage.draft.'.$key] ?? $settings['homepage.'.$key] ?? $default) : ($settings['homepage.'.$key] ?? $default));
    $annEnabled = ($isDraft ? ($settings['homepage.draft.announcement_enabled'] ?? $settings['homepage.announcement_enabled'] ?? true) : ($settings['homepage.announcement_enabled'] ?? true));
    $secMarket = ($isDraft ? ($settings['homepage.draft.section_marketplace_visible'] ?? $settings['homepage.section_marketplace_visible'] ?? true) : ($settings['homepage.section_marketplace_visible'] ?? true));
    $secShop = ($isDraft ? ($settings['homepage.draft.section_shop_visible'] ?? $settings['homepage.section_shop_visible'] ?? true) : ($settings['homepage.section_shop_visible'] ?? true));
    $secAdopt = ($isDraft ? ($settings['homepage.draft.section_adoption_visible'] ?? $settings['homepage.section_adoption_visible'] ?? true) : ($settings['homepage.section_adoption_visible'] ?? true));
    $secBreeds = ($isDraft ? ($settings['homepage.draft.section_breeds_visible'] ?? $settings['homepage.section_breeds_visible'] ?? true) : ($settings['homepage.section_breeds_visible'] ?? true));
    $secTrust = ($isDraft ? ($settings['homepage.draft.section_trust_safety_visible'] ?? $settings['homepage.section_trust_safety_visible'] ?? true) : ($settings['homepage.section_trust_safety_visible'] ?? true));
@endphp

<main style="flex:1;min-width:0;padding:24px 32px 24px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Website / Website Manager</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Homepage content</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Edit headlines, banners and calls-to-action, and choose which sections appear. Preview before you publish.</div>
        </div>
        <span class="pstat {{ $isDraft ? '' : 'pub' }}" id="stat">
            {{ $isDraft ? 'Draft · unpublished changes' : 'Published · live' }}
        </span>
        <a class="btn" href="{{ route('admin.homepage.preview') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">Preview staged view</a>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">Live website ↗</a>
    </header>

    {{-- Draft Status Banner if draft exists --}}
    @if($isDraft)
        <div class="card" style="padding:16px 20px;background:#FFFBEB;border-color:#FDE68A;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:36px;height:36px;border-radius:50%;background:#FEF3C7;color:#92400E;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:#92400E">Unpublished Draft Changes Staged</div>
                    <div style="font-size:12px;color:#B45309;margin-top:2px">You have saved draft changes that are not yet visible to the public. You can preview them, publish them live, or discard them.</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <form method="POST" action="{{ route('admin.homepage.discard') }}" onsubmit="return confirm('Discard all staged draft modifications?')">
                    @csrf
                    <button type="submit" class="btn" style="min-height:36px;padding:0 14px;background:#fff;border:1px solid #FECACA;color:#B91C1C;font-size:13px;cursor:pointer">
                        Discard Draft
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.homepage.publish') }}">
                    @csrf
                    <button type="submit" class="btn" style="min-height:36px;padding:0 16px;background:#17603F;border:0;color:#fff;font-size:13px;cursor:pointer">
                        Publish Draft to Live
                    </button>
                </form>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.homepage.update') }}" id="f">
        @csrf
        @method('PUT')

        <div class="cols">
            <div class="stack">
                {{-- Section 1: Announcement Bar --}}
                <section class="card sec">
                    <div style="display:flex;align-items:flex-start;gap:12px">
                        <div style="flex:1">
                            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Announcement bar</h2>
                            <p class="sub-t">Highlighted notice at the very top of the page.</p>
                        </div>
                        <label style="display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;cursor:pointer">
                            <span>Enable bar</span>
                            <span class="sw">
                                <input type="checkbox" role="switch" name="announcement_enabled" value="1" id="abOn" {{ $annEnabled ? 'checked' : '' }}>
                                <i></i>
                            </span>
                        </label>
                    </div>
                    <div class="f">
                        <label for="ab">Announcement text</label>
                        <input
                            class="inp"
                            name="announcement_text"
                            id="ab"
                            value="{{ $val('announcement_text', 'Connecting ethical breeders, local shelters, and loving families across the community.') }}"
                            maxlength="140"
                            required
                        >
                        @error('announcement_text') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </section>

                {{-- Section 2: Hero Section --}}
                <section class="card sec">
                    <div style="display:flex;align-items:flex-start;gap:12px">
                        <div style="flex:1">
                            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Hero section</h2>
                            <p class="sub-t">Main headline, supporting text and the three calls-to-action.</p>
                        </div>
                    </div>
                    <div class="f">
                        <label for="hh">Headline</label>
                        <input
                            class="inp"
                            name="hero_headline"
                            id="hh"
                            value="{{ $val('hero_headline', 'Your next best friend is waiting.') }}"
                            maxlength="80"
                            required
                        >
                        @error('hero_headline') <div class="err">{{ $message }}</div> @enderror
                    </div>
                    <div class="f" style="margin-top:16px">
                        <label for="hp">Supporting paragraph</label>
                        <textarea
                            class="inp"
                            name="hero_supporting"
                            id="hp"
                            required
                        >{{ $val('hero_supporting', 'Discover pets, shop food and accessories, and help animals find loving homes—all in one place.') }}</textarea>
                        @error('hero_supporting') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="g3b" style="margin-top:18px">
                        {{-- Primary CTA --}}
                        <div class="cta">
                            <div class="lbl" style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#5B6460;margin-bottom:12px">Primary CTA · Marketplace</div>
                            <div class="f">
                                <label for="c1l">Button label</label>
                                <input class="inp" name="cta_primary_label" id="c1l" value="{{ $val('cta_primary_label', 'Explore Pets') }}" required>
                            </div>
                            <div class="f" style="margin-top:12px">
                                <label for="c1u">Target link</label>
                                <input class="inp" name="cta_primary_url" id="c1u" value="{{ $val('cta_primary_url', '#find-pets') }}" required>
                            </div>
                        </div>

                        {{-- Secondary CTA --}}
                        <div class="cta">
                            <div class="lbl" style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#5B6460;margin-bottom:12px">Secondary CTA · Pet shop</div>
                            <div class="f">
                                <label for="c2l">Button label</label>
                                <input class="inp" name="cta_secondary_label" id="c2l" value="{{ $val('cta_secondary_label', 'Shop Pet Supplies') }}" required>
                            </div>
                            <div class="f" style="margin-top:12px">
                                <label for="c2u">Target link</label>
                                <input class="inp" name="cta_secondary_url" id="c2u" value="{{ $val('cta_secondary_url', '#pet-shop') }}" required>
                            </div>
                        </div>

                        {{-- Adoption CTA --}}
                        <div class="cta">
                            <div class="lbl" style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#5B6460;margin-bottom:12px">Adoption CTA · Rescue</div>
                            <div class="f">
                                <label for="c3l">Button label</label>
                                <input class="inp" name="cta_adoption_label" id="c3l" value="{{ $val('cta_adoption_label', 'Explore Adoption') }}" required>
                            </div>
                            <div class="f" style="margin-top:12px">
                                <label for="c3u">Target link</label>
                                <input class="inp" name="cta_adoption_url" id="c3u" value="{{ $val('cta_adoption_url', '#adoption-rescue') }}" required>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section 3: Section Visibility --}}
                <section class="card sec">
                    <div style="display:flex;align-items:flex-start;gap:12px">
                        <div style="flex:1">
                            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Section visibility</h2>
                            <p class="sub-t">Turn major homepage sections on or off as inventory and programs grow.</p>
                        </div>
                    </div>

                    <div class="g2">
                        <div class="vis">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:14px" id="v1t">Pet Marketplace Grid</div>
                                <div style="font-size:12px;color:#5B6460;margin-top:2px">Live pet listings section</div>
                            </div>
                            <label class="sw">
                                <input type="checkbox" role="switch" name="section_marketplace_visible" value="1" id="v1" {{ $secMarket ? 'checked' : '' }} aria-labelledby="v1t">
                                <i></i>
                            </label>
                        </div>

                        <div class="vis">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:14px" id="v2t">Pet Supplies Store</div>
                                <div style="font-size:12px;color:#5B6460;margin-top:2px">Single-shop products showcase</div>
                            </div>
                            <label class="sw">
                                <input type="checkbox" role="switch" name="section_shop_visible" value="1" id="v2" {{ $secShop ? 'checked' : '' }} aria-labelledby="v2t">
                                <i></i>
                            </label>
                        </div>

                        <div class="vis">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:14px" id="v3t">Adoption &amp; Rescue Mission</div>
                                <div style="font-size:12px;color:#5B6460;margin-top:2px">Shelters &amp; rehoming network</div>
                            </div>
                            <label class="sw">
                                <input type="checkbox" role="switch" name="section_adoption_visible" value="1" id="v3" {{ $secAdopt ? 'checked' : '' }} aria-labelledby="v3t">
                                <i></i>
                            </label>
                        </div>

                        <div class="vis">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:14px" id="v4t">Breed Directory Teaser</div>
                                <div style="font-size:12px;color:#5B6460;margin-top:2px">Curated breed profiles</div>
                            </div>
                            <label class="sw">
                                <input type="checkbox" role="switch" name="section_breeds_visible" value="1" id="v4" {{ $secBreeds ? 'checked' : '' }} aria-labelledby="v4t">
                                <i></i>
                            </label>
                        </div>

                        <div class="vis">
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:14px" id="v5t">Trust &amp; Safety Standards</div>
                                <div style="font-size:12px;color:#5B6460;margin-top:2px">Moderation integrity cards</div>
                            </div>
                            <label class="sw">
                                <input type="checkbox" role="switch" name="section_trust_safety_visible" value="1" id="v5" {{ $secTrust ? 'checked' : '' }} aria-labelledby="v5t">
                                <i></i>
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Right Sticky Preview Sidebar --}}
            <aside class="pv stack" aria-label="Live preview" style="display:flex;flex-direction:column;gap:16px">
                <div class="card" style="overflow:hidden">
                    <div style="padding:14px 20px 10px"><div class="lbl" style="letter-spacing:.06em;margin:0">HOMEPAGE PREVIEW</div></div>
                    <div id="pab" style="background:#17603F;color:#fff;font-size:11.5px;text-align:center;padding:8px 12px;line-height:1.4"></div>
                    <div style="padding:22px 20px;background:#F4F6F5">
                        <div id="phh" style="font-size:22px;font-weight:700;letter-spacing:-.02em;line-height:1.2;color:#101814"></div>
                        <div id="php" style="font-size:13px;color:#3B4540;margin:8px 0 16px;line-height:1.5"></div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <span class="btn" id="pb1" style="background:#17603F;color:#fff;min-height:36px;font-size:13px;cursor:default"></span>
                            <span class="btn" id="pb2" style="background:#fff;border:1px solid #17603F;color:#17603F;min-height:36px;font-size:13px;cursor:default"></span>
                            <span class="btn" id="pb3" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420;min-height:36px;font-size:13px;cursor:default"></span>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:20px">
                    <div class="lbl" style="letter-spacing:.06em">SECTIONS ON HOMEPAGE (<span id="vc">5</span> of 5)</div>
                    <div id="sl" style="display:flex;flex-direction:column;gap:6px"></div>
                </div>
            </aside>
        </div>

        {{-- Sticky Bottom Action Bar --}}
        <div class="bar" style="margin-top:24px">
            <a class="btn" href="{{ route('admin.dashboard') }}" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420;margin-right:auto">
                ← Back to dashboard
            </a>
            <span id="dirty" style="font-size:13px;color:#5B6460"></span>
            <button
                class="btn"
                type="submit"
                formaction="{{ route('admin.homepage.draft') }}"
                id="draft"
                style="background:#fff;border:1px solid #D3D9D5;color:#1B2420;cursor:pointer"
            >
                Save as draft
            </button>
            <button
                class="btn"
                type="submit"
                id="pub"
                style="background:#17603F;color:#fff;border:0;cursor:pointer"
            >
                Publish immediately
            </button>
        </div>
    </form>
</main>

<style>
.cols { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:20px; align-items:start; }
.stack { display:flex; flex-direction:column; gap:20px; }
.sec { padding:24px; }
.sec h2 { margin:0; font-size:16px; font-weight:700; color:#101814; }
.sec .sub-t { margin:2px 0 18px; font-size:13px; color:#5B6460; }
.g2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.sw { position:relative; display:inline-block; width:40px; height:24px; flex:none; }
.sw input { opacity:0; width:0; height:0; position:absolute; }
.sw i { position:absolute; inset:0; background:#D3D9D5; border-radius:999px; cursor:pointer; transition:.15s; }
.sw i::after { content:""; position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.25); transition:transform .15s; }
.sw input:checked+i { background:#17603F; }
.sw input:checked+i::after { transform:translateX(16px); }
.sw input:focus-visible+i { outline:2px solid #17603F; outline-offset:2px; }
.vis { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid #E3E7E4; border-radius:10px; background:#fff; }
.cta { border:1px solid #E3E7E4; border-radius:10px; padding:16px; background:#FAFBFA; }
.pstat { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:#FFF1DC; color:#8A4B06; font-size:12px; font-weight:600; }
.pstat.pub { background:#E3F3E9; color:#14573A; }
.sc { display:flex; align-items:center; gap:8px; font-size:12.5px; padding:7px 10px; border-radius:6px; background:#EEF5F0; color:#14573A; font-weight:500; }
.sc.off { background:#F1F2F1; color:#8A938E; text-decoration:line-through; }
.g3b { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.pv { position:sticky; top:20px; }
.bar { position:sticky; bottom:0; display:flex; align-items:center; gap:12px; justify-content:flex-end; padding:14px 20px; background:rgba(255,255,255,.92); backdrop-filter:blur(6px); border:1px solid #E3E7E4; border-radius:10px; box-shadow:0 -2px 12px rgba(16,24,20,.06); z-index:30; }
@media(max-width:1100px){ .cols { grid-template-columns:1fr; } .pv { position:static; } }
@media(max-width:1000px){ .g3b { grid-template-columns:1fr; } }
@media(max-width:700px){ .g2 { grid-template-columns:1fr; } }
</style>

<script>
var $ = function(i) { return document.getElementById(i); };
var V = ['v1', 'v2', 'v3', 'v4', 'v5'];

function render() {
    $('pab').textContent = $('ab').value;
    $('pab').style.display = $('abOn').checked ? 'block' : 'none';
    $('phh').textContent = $('hh').value;
    $('php').textContent = $('hp').value;
    $('pb1').textContent = $('c1l').value || 'Button';
    $('pb2').textContent = $('c2l').value || 'Button';
    $('pb3').textContent = $('c3l').value || 'Button';

    var sl = $('sl');
    sl.innerHTML = '';
    var n = 0;
    V.forEach(function(v) {
        var on = $(v).checked;
        if (on) n++;
        var d = document.createElement('div');
        d.className = 'sc' + (on ? '' : ' off');
        d.textContent = ($(v + 't').textContent) + (on ? '' : ' (hidden)');
        sl.appendChild(d);
    });
    $('vc').textContent = n;
}

document.querySelectorAll('main input, main textarea').forEach(function(el) {
    el.addEventListener('input', function() {
        render();
        $('dirty').textContent = 'Unsaved changes';
    });
});
document.querySelectorAll('main input[type=checkbox]').forEach(function(el) {
    el.addEventListener('change', function() {
        render();
        $('dirty').textContent = 'Unsaved changes';
    });
});

render();
</script>
@endsection
