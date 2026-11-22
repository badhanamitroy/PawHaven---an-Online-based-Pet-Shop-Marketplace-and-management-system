<x-layouts::admin title="Brand identity" breadcrumbs="Website / Brand & Logos">
    <style>
        .cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
        .stack { display: flex; flex-direction: column; gap: 20px; }
        .sec { padding: 24px; }
        .sec h2 { margin: 0; font-size: 16px; color: #101814; font-weight: 700; }
        .sec .sub-t { margin: 2px 0 18px; font-size: 13px; color: #5B6460; }
        .g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .g3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .g4 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        @media(min-width: 900px) { .g4 { grid-template-columns: repeat(2, 1fr); } }
        .f label, .lbl { display: block; font-size: 12px; font-weight: 600; color: #3B4540; margin-bottom: 6px; }
        .inp { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #D3D9D5; border-radius: 8px; background: #fff; font: inherit; font-size: 14px; color: #101814; box-sizing: border-box; }
        textarea.inp { padding: 10px 12px; min-height: 96px; resize: vertical; line-height: 1.5; }
        .inp:focus, .hex:focus { outline: 2px solid #17603F; outline-offset: 1px; border-color: #17603F; }
        .hint { font-size: 12px; color: #5B6460; margin-top: 6px; }
        .clr { display: flex; gap: 8px; }
        .clr input[type=color] { width: 44px; height: 44px; padding: 3px; border: 1px solid #D3D9D5; border-radius: 8px; background: #fff; cursor: pointer; flex: none; box-sizing: border-box; }
        .hex { flex: 1; min-width: 0; min-height: 44px; padding: 0 12px; border: 1px solid #D3D9D5; border-radius: 8px; font: inherit; font-size: 14px; font-family: ui-monospace, Menlo, monospace; text-transform: uppercase; background: #fff; box-sizing: border-box; }

        .tile { border: 1.5px dashed #C4CCC7; border-radius: 10px; height: 110px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #F4F6F5; position: relative; }
        .tile.dk { background: #0F2A20; border-color: #2C4A3C; }
        .tile.navy { background: #0A192F; border-color: #1E3A8A; }
        .tile img { max-width: 85%; max-height: 80%; object-fit: contain; }

        .banner-tile { border: 1.5px dashed #C4CCC7; border-radius: 10px; height: 210px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #1B2E24; position: relative; }
        .banner-tile img { width: 100%; height: 100%; object-fit: cover; }

        .up { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 14px; border: 1px solid #D3D9D5; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 10px; color: #1B2420; }
        .up:hover { background: #F4F6F5; }
        .up input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
        .up:focus-within { outline: 2px solid #17603F; outline-offset: 1px; }

        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #E3F3E9; color: #14573A; font-size: 12px; font-weight: 600; }
        .pill i { width: 7px; height: 7px; border-radius: 50%; background: #1F9D5C; }
        .pv { position: sticky; top: 20px; }
        .mark { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px; flex: none; }
        .bar { position: sticky; bottom: 0; display: flex; align-items: center; gap: 12px; justify-content: flex-end; padding: 14px 20px; background: rgba(255,255,255,.94); backdrop-filter: blur(6px); border: 1px solid #E3E7E4; border-radius: 10px; box-shadow: 0 -2px 12px rgba(16,24,20,.06); z-index: 30; }

        .meta-tag { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #E8ECE9; color: #3B4540; font-family: ui-monospace, Menlo, monospace; }
        .preview-badge { position: absolute; top: 8px; right: 8px; background: #17603F; color: #fff; font-size: 10px; font-weight: 700; padding: 3px 7px; border-radius: 4px; text-transform: uppercase; letter-spacing: .05em; }

        /* Interactive Paw Cursor Demo Box */
        .cursor-demo-box {
            border: 2px dashed #9BB5A7;
            background: #F0F6F2;
            border-radius: 10px;
            padding: 18px;
            cursor: url('{{ \App\Services\MediaManager::cursorUrl('svg') }}') 5 5, url('{{ \App\Services\MediaManager::cursorUrl('png') }}') 5 5, auto !important;
            transition: border-color .2s;
        }
        .cursor-demo-box:hover {
            border-color: #17603F;
            background: #EAF4EE;
        }
        .cursor-demo-box * {
            cursor: inherit;
        }
        .cursor-demo-box input, .cursor-demo-box textarea {
            cursor: text !important;
        }
        .cursor-demo-box p {
            cursor: text !important;
        }

        @media(max-width: 1100px) { .cols { grid-template-columns: 1fr; } .pv { position: static; } }
        @media(max-width: 700px) { .g2, .g3, .g4 { grid-template-columns: 1fr; } }
    </style>

    <main style="flex:1;min-width:0;padding:24px 32px 24px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
        {{-- A. Page Header --}}
        <header style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <div style="font-size:12px;color:#5B6460">Website / Brand &amp; Logos</div>
                <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Brand identity &amp; media</h1>
                <div style="font-size:13px;color:#5B6460;margin-top:2px">Manage company identity, canonical brand media (Hero Banner, Logos), custom paw cursor, and search metadata.</div>
            </div>
            <span class="pill"><i></i>Live on website</span>
            <a href="{{ route('public.home') }}" target="_blank" style="font-size:13px;font-weight:600;color:#17603F;display:inline-flex;align-items:center;gap:4px">
                View public store ↗
            </a>
        </header>

        {{-- Session Feedback Alerts --}}
        @if(session('success') || session('status'))
            <div style="padding:14px 18px;border-radius:8px;background:#E3F3E9;border:1px solid #B4DFCA;color:#0F5132;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px">
                <svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('success') ?? session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div style="padding:14px 18px;border-radius:8px;background:#FDF2F2;border:1px solid #F8B4B4;color:#9B1C1C;font-size:14px;display:flex;flex-direction:column;gap:6px">
                <div style="font-weight:700">Please review the following issues:</div>
                <ul style="margin:0;padding-left:20px;font-size:13px">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Configuration & Live Preview Layout --}}
        <form id="brandForm" method="POST" action="{{ route('admin.brand.update') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
            @csrf
            @method('PUT')

            <div class="cols">
                <div class="stack">
                    {{-- B. Company Details --}}
                    <section class="card sec">
                        <h2>Company details</h2>
                        <p class="sub-t">How your brand appears across the store and community.</p>
                        <div class="g2">
                            <div class="f">
                                <label for="name">Company / brand name</label>
                                <input class="inp" id="name" name="brand_name" value="{{ old('brand_name', $settings['brand.name'] ?? 'PawHaven') }}" required>
                                @error('brand_name') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="tag">Tagline</label>
                                <input class="inp" id="tag" name="brand_tagline" value="{{ old('brand_tagline', $settings['brand.tagline'] ?? 'Find Pets. Give Hope. Shop with Love.') }}" required>
                                @error('brand_tagline') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- C. Brand Colors --}}
                    <section class="card sec">
                        <h2>Brand colors</h2>
                        <p class="sub-t">Used for buttons, links, accents and the admin sidebar navigation.</p>
                        <div class="g3">
                            <div class="f">
                                <label for="c1h">Primary</label>
                                <div class="clr">
                                    <input type="color" id="c1" value="{{ old('brand_primary_color', $settings['brand.primary_color'] ?? '#17603F') }}" aria-label="Primary picker">
                                    <input class="hex" id="c1h" name="brand_primary_color" value="{{ old('brand_primary_color', $settings['brand.primary_color'] ?? '#17603F') }}" maxlength="7" required>
                                </div>
                                @error('brand_primary_color') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="c2h">Secondary</label>
                                <div class="clr">
                                    <input type="color" id="c2" value="{{ old('brand_secondary_color', $settings['brand.secondary_color'] ?? '#0F2A20') }}" aria-label="Secondary picker">
                                    <input class="hex" id="c2h" name="brand_secondary_color" value="{{ old('brand_secondary_color', $settings['brand.secondary_color'] ?? '#0F2A20') }}" maxlength="7" required>
                                </div>
                                @error('brand_secondary_color') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="c3h">Accent</label>
                                <div class="clr">
                                    <input type="color" id="c3" value="{{ old('brand_accent_color', $settings['brand.accent_color'] ?? '#C2620C') }}" aria-label="Accent picker">
                                    <input class="hex" id="c3h" name="brand_accent_color" value="{{ old('brand_accent_color', $settings['brand.accent_color'] ?? '#C2620C') }}" maxlength="7" required>
                                </div>
                                @error('brand_accent_color') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- D. Dynamic Hero Banner Management --}}
                    <section class="card sec">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div>
                                <h2>Homepage hero banner</h2>
                                <p class="sub-t" style="margin-bottom:12px">Canonical location: <code>public/Media/Images/Hero-Banner.png</code>. Displayed on the public storefront homepage.</p>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <span class="meta-tag">{{ $mediaAssets['hero_banner']['dimensions'] ?? '1920x640' }}</span>
                                <span class="meta-tag">{{ $mediaAssets['hero_banner']['size_formatted'] ?? '1.86 MB' }}</span>
                                <span class="meta-tag">Auto-cache buster: active</span>
                            </div>
                        </div>

                        {{-- Current Hero Banner Preview Tile --}}
                        <div class="banner-tile" id="t_hero">
                            <img src="{{ \App\Services\MediaManager::heroBannerUrl() }}" alt="Current Hero Banner" id="img_hero">
                            <span class="preview-badge" id="badge_hero" style="display:none">Replacement Selected</span>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px">
                            <div>
                                <label class="up" style="margin-top:0">
                                    <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" style="margin-right:8px;color:#17603F">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Replace Hero Banner
                                    <input type="file" id="hero_banner" name="hero_banner" accept="image/png,image/jpeg,image/webp">
                                </label>
                                <span id="hero_filename" style="font-size:12px;color:#5B6460;margin-left:8px">Retains canonical filename <code>Hero-Banner.png</code></span>
                            </div>
                            <div class="hint" style="margin-top:0">PNG, JPG, or WebP. Max 10 MB. Recommended ratio: 16:9 or 21:9 (1920x640+).</div>
                        </div>
                        @error('hero_banner') <p style="font-size:12px;color:#B42318;margin-top:6px">{{ $message }}</p> @enderror
                    </section>

                    {{-- E. Brand Logo & Icon Assets (4 Canonical Media Items) --}}
                    <section class="card sec">
                        <h2>Logo &amp; icon assets</h2>
                        <p class="sub-t">Canonical directory: <code>public/Media/Logos/</code>. All replacements retain exact canonical filenames and auto-invalidate browser cache via <code>?v=timestamp</code>.</p>

                        <div class="g4">
                            {{-- 1. Primary Green Logo --}}
                            <div class="f">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <span class="lbl" style="margin-bottom:0">1. Primary Green Logo</span>
                                    <span class="meta-tag">{{ $mediaAssets['logo_green']['size_formatted'] ?? '42.8 KB' }}</span>
                                </div>
                                <div class="tile" id="t_lg1">
                                    <img src="{{ \App\Services\MediaManager::logoUrl('green') }}" alt="Current Primary Green Logo" id="img_lg1">
                                    <span class="preview-badge" id="badge_lg1" style="display:none">New</span>
                                </div>
                                <label class="up" style="width:100%;box-sizing:border-box">
                                    Choose replacement
                                    <input type="file" id="lg1" name="logo_light" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                </label>
                                <div class="hint"><strong>Usage:</strong> Public website header, marketplace navbar, and light cards. (<code>PawHeaven-Green-logo.png</code>)</div>
                                @error('logo_light') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>

                            {{-- 2. Dark / White Logo --}}
                            <div class="f">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <span class="lbl" style="margin-bottom:0">2. White / Dark Logo</span>
                                    <span class="meta-tag">{{ $mediaAssets['logo_white']['size_formatted'] ?? '42.8 KB' }}</span>
                                </div>
                                <div class="tile dk" id="t_lg2">
                                    <img src="{{ \App\Services\MediaManager::logoUrl('white') }}" alt="Current Dark / White Logo" id="img_lg2">
                                    <span class="preview-badge" id="badge_lg2" style="display:none">New</span>
                                </div>
                                <label class="up" style="width:100%;box-sizing:border-box">
                                    Choose replacement
                                    <input type="file" id="lg2" name="logo_dark" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                </label>
                                <div class="hint"><strong>Usage:</strong> Dark admin sidebar, website footer, auth side panels. (<code>PawHeaven-White-logo.png</code>)</div>
                                @error('logo_dark') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>

                            {{-- 3. Navy Blue Logo --}}
                            <div class="f">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <span class="lbl" style="margin-bottom:0">3. Navy Blue Logo</span>
                                    <span class="meta-tag">{{ $mediaAssets['logo_navy']['size_formatted'] ?? '42.8 KB' }}</span>
                                </div>
                                <div class="tile navy" id="t_lg_navy">
                                    <img src="{{ \App\Services\MediaManager::logoUrl('navy') }}" alt="Current Navy Blue Logo" id="img_lg_navy">
                                    <span class="preview-badge" id="badge_lg_navy" style="display:none">New</span>
                                </div>
                                <label class="up" style="width:100%;box-sizing:border-box">
                                    Choose replacement
                                    <input type="file" id="lg_navy" name="logo_navy" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                </label>
                                <div class="hint"><strong>Usage:</strong> Customer invoices, receipts, and official printouts. (<code>PawHeaven-NavyBlue-logo.png</code>)</div>
                                @error('logo_navy') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>

                            {{-- 4. Favicon & Brand Icon --}}
                            <div class="f">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <span class="lbl" style="margin-bottom:0">4. Brand Icon &amp; Favicon</span>
                                    <span class="meta-tag">{{ $mediaAssets['logo_icon']['size_formatted'] ?? '16.7 KB' }}</span>
                                </div>
                                <div class="tile" id="t_lg3">
                                    <img src="{{ \App\Services\MediaManager::iconUrl() }}" alt="Current Brand Icon" style="width:48px;height:48px" id="img_lg3">
                                    <span class="preview-badge" id="badge_lg3" style="display:none">New</span>
                                </div>
                                <label class="up" style="width:100%;box-sizing:border-box">
                                    Choose replacement
                                    <input type="file" id="lg3" name="favicon" accept="image/x-icon,image/png,image/svg+xml">
                                </label>
                                <div class="hint"><strong>Usage:</strong> Browser tab favicon, bookmarks, app shortcut. (<code>PawHeaven-icon.png</code>)</div>
                                @error('favicon') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- F. Custom Paw Cursor Settings & Live Sandbox --}}
                    <section class="card sec">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div>
                                <h2>Custom Paw Cursor</h2>
                                <p class="sub-t" style="margin-bottom:14px">Playful, high-contrast silhouette paw cursor applied across public website interactions.</p>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px">
                                <img src="{{ \App\Services\MediaManager::cursorUrl('svg') }}" alt="Paw Cursor" width="24" height="24" style="filter:drop-shadow(0 1px 2px rgba(0,0,0,0.2))">
                                <span class="meta-tag">public/Media/paw-cursor.svg</span>
                            </div>
                        </div>

                        <div class="g2" style="align-items:start">
                            {{-- Radio Toggle Options --}}
                            <div style="display:flex;flex-direction:column;gap:10px">
                                @php
                                    $cursorEnabled = (bool) ($settings['brand.custom_paw_cursor'] ?? false);
                                @endphp
                                <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid {{ $cursorEnabled ? '#17603F' : '#D3D9D5' }};border-radius:10px;background:{{ $cursorEnabled ? '#F0F7F3' : '#fff' }};cursor:pointer" id="lbl_cursor_on">
                                    <input type="radio" name="custom_paw_cursor" value="1" id="cur_on" {{ $cursorEnabled ? 'checked' : '' }} style="margin-top:3px;accent-color:#17603F">
                                    <div>
                                        <div style="font-size:14px;font-weight:700;color:#101814">Enabled (Active on Public Website)</div>
                                        <div style="font-size:12px;color:#5B6460;margin-top:2px;line-height:1.4">
                                            Replaces the default arrow with the branded PawHaven paw cursor across desktop browsers (<code>pointer: fine</code>).
                                        </div>
                                    </div>
                                </label>

                                <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid {{ !$cursorEnabled ? '#17603F' : '#D3D9D5' }};border-radius:10px;background:{{ !$cursorEnabled ? '#F0F7F3' : '#fff' }};cursor:pointer" id="lbl_cursor_off">
                                    <input type="radio" name="custom_paw_cursor" value="0" id="cur_off" {{ !$cursorEnabled ? 'checked' : '' }} style="margin-top:3px;accent-color:#17603F">
                                    <div>
                                        <div style="font-size:14px;font-weight:700;color:#101814">Disabled (Native Browser Default)</div>
                                        <div style="font-size:12px;color:#5B6460;margin-top:2px;line-height:1.4">
                                            Uses standard system mouse pointer. Default setting.
                                        </div>
                                    </div>
                                </label>

                                <div class="hint" style="line-height:1.4">
                                    <strong>Non-intrusive design:</strong> Native I-beam text selection is always preserved on body text, paragraphs, and inputs. Touchscreens/mobile interactions are completely unaffected.
                                </div>
                            </div>

                            {{-- Interactive Test Sandbox --}}
                            <div class="cursor-demo-box" id="cursor_sandbox" title="Hover over this box to test the Paw Cursor in action!">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                                    <img src="{{ \App\Services\MediaManager::cursorUrl('svg') }}" alt="Paw" width="20" height="20">
                                    <span style="font-size:13px;font-weight:700;color:#17603F">Interactive Paw Cursor Sandbox</span>
                                </div>
                                <p style="font-size:12px;color:#3B4540;margin:0 0 12px;line-height:1.4">
                                    Move your mouse inside this card to test the paw cursor! Try hovering the button, link, and text input below:
                                </p>
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                                    <button type="button" class="btn" style="min-height:34px;padding:0 12px;font-size:12px;background:#17603F;color:#fff;border:0">Sample Button</button>
                                    <a href="#" onclick="return false;" style="font-size:12px;font-weight:600;color:#17603F">Interactive Link ↗</a>
                                </div>
                                <input class="inp" type="text" placeholder="I-beam text selection is preserved here..." style="min-height:36px;font-size:12px">
                            </div>
                        </div>
                    </section>

                    {{-- G. Contact & Social Links --}}
                    <section class="card sec">
                        <h2>Contact &amp; social links</h2>
                        <p class="sub-t">Shown in the footer and on the contact page.</p>
                        <div class="g2">
                            <div class="f">
                                <label for="em">Customer support email</label>
                                <input class="inp" id="em" name="brand_contact_email" type="email" placeholder="support@pawhaven.com" value="{{ old('brand_contact_email', $settings['brand.contact_email'] ?? '') }}" required>
                                @error('brand_contact_email') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="ph">Support phone number</label>
                                <input class="inp" id="ph" name="brand_phone" type="tel" placeholder="+880 ..." value="{{ old('brand_phone', $settings['brand.phone'] ?? '') }}">
                                @error('brand_phone') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="f" style="margin-top:16px">
                            <label for="ad">Physical business address</label>
                            <input class="inp" id="ad" name="brand_address" value="{{ old('brand_address', $settings['brand.address'] ?? '') }}">
                            @error('brand_address') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                        </div>
                        <div class="g3" style="margin-top:16px">
                            <div class="f">
                                <label for="fb">Facebook URL</label>
                                <input class="inp" id="fb" name="brand_social_facebook" type="url" placeholder="https://facebook.com/pawhaven" value="{{ old('brand_social_facebook', $settings['brand.social_facebook'] ?? '') }}">
                                @error('brand_social_facebook') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="ig">Instagram URL</label>
                                <input class="inp" id="ig" name="brand_social_instagram" type="url" placeholder="https://instagram.com/pawhaven" value="{{ old('brand_social_instagram', $settings['brand.social_instagram'] ?? '') }}">
                                @error('brand_social_instagram') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                            <div class="f">
                                <label for="x">X / Twitter URL</label>
                                <input class="inp" id="x" name="brand_social_x" type="url" placeholder="https://x.com/pawhaven" value="{{ old('brand_social_x', $settings['brand.social_x'] ?? '') }}">
                                @error('brand_social_x') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- H. Search & Social Metadata --}}
                    <section class="card sec">
                        <h2>Search &amp; social metadata</h2>
                        <p class="sub-t">Controls how the site appears in Google and when shared on social networks.</p>
                        <div class="f">
                            <label for="meta">Meta description</label>
                            <textarea class="inp" id="meta" name="brand_meta_description" required>{{ old('brand_meta_description', $settings['brand.meta_description'] ?? 'PawHaven is a premium pet marketplace, adoption and rescue community, breed encyclopedia, and pet supplies shop.') }}</textarea>
                            <div class="hint"><span id="cnt">0</span> / 160 characters</div>
                            @error('brand_meta_description') <p style="font-size:12px;color:#B42318;margin-top:4px">{{ $message }}</p> @enderror
                        </div>
                    </section>
                </div>

                {{-- Live Preview Sticky Sidebar --}}
                <aside class="pv stack" aria-label="Live preview" style="display:flex;flex-direction:column;gap:16px">
                    {{-- 1. Live Preview Light --}}
                    <div class="card" style="padding:20px">
                        <div class="lbl" style="letter-spacing:.06em">LIVE PREVIEW · STOREFRONT HEADER</div>
                        <div style="border:1px solid #E3E7E4;border-radius:10px;padding:14px;display:flex;align-items:center;gap:12px;background:#FAF8F2">
                            <div id="pimg1" style="flex-shrink:0">
                                <img src="{{ \App\Services\MediaManager::logoUrl('green') }}" alt="" style="height:32px;max-width:110px;object-fit:contain" id="prev_logo_light">
                            </div>
                            <span class="mark pm" id="pm1" style="display:none">P</span>
                            <div style="min-width:0;flex:1">
                                <div id="pn1" style="font-weight:700;font-size:15px;color:#101814;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $settings['brand.name'] ?? 'PawHaven' }}</div>
                                <div id="pt1" style="font-size:11px;color:#5B6460;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $settings['brand.tagline'] ?? 'Find Pets. Give Hope. Shop with Love.' }}</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:12px">
                            <span class="btn pbtn" style="background:var(--c1,#17603F);color:#fff;min-height:36px;font-size:13px;padding:0 14px">Primary button</span>
                            <span class="btn" style="border:1.5px solid var(--c3,#C2620C);color:var(--c3,#C2620C);background:transparent;min-height:36px;font-size:13px;padding:0 14px">Accent</span>
                        </div>
                    </div>

                    {{-- 2. Live Preview Dark --}}
                    <div class="card" style="padding:20px">
                        <div class="lbl" style="letter-spacing:.06em">LIVE PREVIEW · DARK SIDEBAR / FOOTER</div>
                        <div id="dk" style="border-radius:10px;padding:14px;display:flex;align-items:center;gap:12px;background:#0F2A20">
                            <div id="pimg2" style="flex-shrink:0">
                                <img src="{{ \App\Services\MediaManager::logoUrl('white') }}" alt="" style="height:32px;max-width:110px;object-fit:contain" id="prev_logo_dark">
                            </div>
                            <span class="mark" id="pm2" style="background:#fff;display:none">P</span>
                            <div style="min-width:0;flex:1">
                                <div id="pn2" style="font-weight:700;font-size:15px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $settings['brand.name'] ?? 'PawHaven' }}</div>
                                <div style="font-size:11px;letter-spacing:.08em;color:#9DB8AA">ADMIN PORTAL</div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Live Preview Hero Thumbnail --}}
                    <div class="card" style="padding:20px">
                        <div class="lbl" style="letter-spacing:.06em">HOMEPAGE HERO BANNER PREVIEW</div>
                        <div style="border-radius:8px;overflow:hidden;height:95px;background:#173F32;position:relative">
                            <img src="{{ \App\Services\MediaManager::heroBannerUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover" id="prev_hero_thumb">
                            <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(0,0,0,0.5), transparent);display:flex;align-items:flex-end;padding:8px">
                                <span style="font-size:11px;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,0.8)">Active Hero Banner</span>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Custom Paw Cursor Status --}}
                    <div class="card" style="padding:20px">
                        <div class="lbl" style="letter-spacing:.06em">CUSTOM PAW CURSOR STATUS</div>
                        <div style="display:flex;align-items:center;gap:12px">
                            <div style="width:38px;height:38px;border-radius:8px;background:#EAF4EE;display:flex;align-items:center;justify-content:center;border:1px solid #B4DFCA">
                                <img src="{{ \App\Services\MediaManager::cursorUrl('svg') }}" alt="" width="22" height="22">
                            </div>
                            <div>
                                <div id="cur_status_text" style="font-weight:700;font-size:14px;color:{{ $cursorEnabled ? '#17603F' : '#5B6460' }}">
                                    {{ $cursorEnabled ? 'Enabled on Storefront' : 'Disabled (Native Arrow)' }}
                                </div>
                                <div style="font-size:11px;color:#5B6460">Desktop &amp; mouse devices</div>
                            </div>
                        </div>
                    </div>

                    {{-- 5. Search Result Preview --}}
                    <div class="card" style="padding:20px">
                        <div class="lbl" style="letter-spacing:.06em">SEARCH RESULT PREVIEW</div>
                        <div style="font-size:12px;color:#5B6460">{{ request()->getHost() }} › home</div>
                        <div id="st" style="font-size:16px;color:#1A56B0;margin:2px 0;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $settings['brand.name'] ?? 'PawHaven' }} | {{ $settings['brand.tagline'] ?? 'Find Pets. Give Hope. Shop with Love.' }}</div>
                        <div id="sd" style="font-size:13px;color:#3B4540;line-height:1.5;word-break:break-word"></div>
                    </div>
                </aside>
            </div>

            {{-- Sticky Save Action Bar --}}
            <div class="bar">
                <span id="dirty" style="margin-right:auto;font-size:13px;color:#5B6460;font-weight:500">All changes saved</span>
                <button class="btn" type="button" id="cancel" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420;cursor:pointer">Cancel</button>
                <button class="btn" type="submit" id="save" style="background:#17603F;color:#fff;border:0;cursor:pointer">Save brand settings</button>
            </div>
        </form>
    </main>

    {{-- Live Preview & Form Synchronization JavaScript --}}
    <script>
        (function() {
            var $ = function(id) { return document.getElementById(id); };

            var initialLogos = {
                lg1: @json(\App\Services\MediaManager::logoUrl('green')),
                lg2: @json(\App\Services\MediaManager::logoUrl('white')),
                navy: @json(\App\Services\MediaManager::logoUrl('navy')),
                lg3: @json(\App\Services\MediaManager::iconUrl()),
                hero: @json(\App\Services\MediaManager::heroBannerUrl())
            };

            var logos = {
                lg1: initialLogos.lg1,
                lg2: initialLogos.lg2,
                navy: initialLogos.navy,
                lg3: initialLogos.lg3,
                hero: initialLogos.hero
            };

            var isDirty = false;
            var objectUrls = [];

            function isValidHex(hex) {
                return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(hex);
            }

            function markDirty() {
                if (!isDirty) {
                    isDirty = true;
                    var dirtyEl = $('dirty');
                    if (dirtyEl) {
                        dirtyEl.textContent = 'Unsaved changes';
                        dirtyEl.style.color = '#B45309';
                    }
                }
            }

            function render() {
                var nameEl = $('name');
                var tagEl = $('tag');
                var c1El = $('c1');
                var c2El = $('c2');
                var c3El = $('c3');
                var metaEl = $('meta');

                var n = (nameEl && nameEl.value.trim()) ? nameEl.value : 'PawHaven';
                var t = tagEl ? tagEl.value : '';
                var c1 = (c1El && isValidHex(c1El.value)) ? c1El.value : '#17603F';
                var c2 = (c2El && isValidHex(c2El.value)) ? c2El.value : '#0F2A20';
                var c3 = (c3El && isValidHex(c3El.value)) ? c3El.value : '#C2620C';

                document.documentElement.style.setProperty('--c1', c1);
                document.documentElement.style.setProperty('--c3', c3);

                var pn1 = $('pn1');
                var pn2 = $('pn2');
                var pt1 = $('pt1');
                if (pn1) pn1.textContent = n;
                if (pn2) pn2.textContent = n;
                if (pt1) pt1.textContent = t;

                var dk = $('dk');
                if (dk) dk.style.background = c2;

                var md = metaEl ? metaEl.value : '';
                var cnt = $('cnt');
                if (cnt) {
                    cnt.textContent = md.length;
                    cnt.style.color = md.length > 160 ? '#B42318' : 'inherit';
                }

                var st = $('st');
                var sd = $('sd');
                if (st) st.textContent = t ? (n + ' | ' + t) : n;
                if (sd) sd.textContent = md.slice(0, 160);

                // Update cursor status in preview sidebar
                var curOn = $('cur_on');
                var curStatus = $('cur_status_text');
                var lblOn = $('lbl_cursor_on');
                var lblOff = $('lbl_cursor_off');
                if (curOn && curStatus) {
                    if (curOn.checked) {
                        curStatus.textContent = 'Enabled on Storefront';
                        curStatus.style.color = '#17603F';
                        if (lblOn) { lblOn.style.borderColor = '#17603F'; lblOn.style.background = '#F0F7F3'; }
                        if (lblOff) { lblOff.style.borderColor = '#D3D9D5'; lblOff.style.background = '#fff'; }
                    } else {
                        curStatus.textContent = 'Disabled (Native Arrow)';
                        curStatus.style.color = '#5B6460';
                        if (lblOff) { lblOff.style.borderColor = '#17603F'; lblOff.style.background = '#F0F7F3'; }
                        if (lblOn) { lblOn.style.borderColor = '#D3D9D5'; lblOn.style.background = '#fff'; }
                    }
                }
            }

            // Bind color pickers & hex inputs
            ['c1', 'c2', 'c3'].forEach(function(k) {
                var picker = $(k);
                var hex = $(k + 'h');
                if (picker && hex) {
                    picker.addEventListener('input', function() {
                        hex.value = picker.value.toUpperCase();
                        render();
                        markDirty();
                    });
                    hex.addEventListener('input', function() {
                        var v = hex.value.trim();
                        if (isValidHex(v)) {
                            picker.value = v.length === 4
                                ? ('#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3])
                                : v;
                            render();
                        }
                        markDirty();
                    });
                }
            });

            // Bind Hero Banner input
            var heroInput = $('hero_banner');
            if (heroInput) {
                heroInput.addEventListener('change', function(e) {
                    var file = e.target.files && e.target.files[0];
                    if (!file) return;

                    var url = URL.createObjectURL(file);
                    objectUrls.push(url);

                    var imgHero = $('img_hero');
                    if (imgHero) imgHero.src = url;

                    var prevThumb = $('prev_hero_thumb');
                    if (prevThumb) prevThumb.src = url;

                    var badgeHero = $('badge_hero');
                    if (badgeHero) badgeHero.style.display = 'block';

                    var nameDisplay = $('hero_filename');
                    if (nameDisplay) {
                        nameDisplay.innerHTML = 'Selected: <strong>' + file.name + '</strong> (' + (file.size / 1024 / 1024).toFixed(2) + ' MB) &rarr; will replace <code>Hero-Banner.png</code>';
                    }

                    render();
                    markDirty();
                });
            }

            // Bind logo file inputs
            var fileConfigs = [
                { inputId: 'lg1', imgId: 'img_lg1', badgeId: 'badge_lg1', prevId: 'prev_logo_light' },
                { inputId: 'lg2', imgId: 'img_lg2', badgeId: 'badge_lg2', prevId: 'prev_logo_dark' },
                { inputId: 'lg_navy', imgId: 'img_lg_navy', badgeId: 'badge_lg_navy', prevId: null },
                { inputId: 'lg3', imgId: 'img_lg3', badgeId: 'badge_lg3', prevId: null }
            ];

            fileConfigs.forEach(function(cfg) {
                var input = $(cfg.inputId);
                if (input) {
                    input.addEventListener('change', function(e) {
                        var file = e.target.files && e.target.files[0];
                        if (!file) return;

                        var url = URL.createObjectURL(file);
                        objectUrls.push(url);

                        var targetImg = $(cfg.imgId);
                        if (targetImg) targetImg.src = url;

                        var badge = $(cfg.badgeId);
                        if (badge) badge.style.display = 'block';

                        if (cfg.prevId) {
                            var prev = $(cfg.prevId);
                            if (prev) prev.src = url;
                        }

                        render();
                        markDirty();
                    });
                }
            });

            // Bind cursor radio inputs
            ['cur_on', 'cur_off'].forEach(function(id) {
                var radio = $(id);
                if (radio) {
                    radio.addEventListener('change', function() {
                        render();
                        markDirty();
                    });
                }
            });

            // General input listener for text inputs and textarea
            document.querySelectorAll('#brandForm input, #brandForm textarea').forEach(function(el) {
                el.addEventListener('input', function() {
                    render();
                    markDirty();
                });
            });

            // Cancel button
            var cancelBtn = $('cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    if (isDirty) {
                        if (confirm('Discard unsaved changes?')) {
                            window.location.reload();
                        }
                    } else {
                        window.location.reload();
                    }
                });
            }

            // Prevent duplicate submissions and update button text during save
            var form = $('brandForm');
            if (form) {
                form.addEventListener('submit', function() {
                    var saveBtn = $('save');
                    if (saveBtn) {
                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Saving brand settings...';
                    }
                });
            }

            // Cleanup object URLs on window unload
            window.addEventListener('beforeunload', function() {
                objectUrls.forEach(function(url) {
                    URL.revokeObjectURL(url);
                });
            });

            // Initial render
            render();
        })();
    </script>
</x-layouts::admin>
