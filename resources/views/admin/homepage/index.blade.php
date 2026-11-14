<x-layouts::admin title="Homepage Customizer" breadcrumbs="Website & Branding / Homepage Customizer">
    <div class="space-y-8">
        {{-- Header Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Website Manager</span>
                <h1 class="text-2xl font-bold tracking-tight text-[#173F32] mt-1">Homepage Content &amp; Section Manager</h1>
                <p class="text-xs sm:text-sm text-[#69736B] mt-1">Safely modify headlines, announcement banners, call-to-actions, and toggle public sections on or off with draft and preview workflows.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.homepage.preview') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-[#245C45] bg-[#245C45]/10 hover:bg-[#245C45]/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Preview Staged View &rarr;</span>
                </a>
                <a href="{{ route('public.home') }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors">
                    <span>Live Website &rarr;</span>
                </a>
            </div>
        </div>

        {{-- Draft Status Banner --}}
        @if(!empty($settings['homepage.draft_exists']))
            <div class="rounded-3xl border border-amber-200 bg-amber-50/80 p-6 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-amber-900">Unpublished Draft Changes Staged</h3>
                        <p class="text-xs text-amber-800/80 mt-0.5">
                            You have saved draft changes that are not yet visible to the public. You can preview them, publish them live, or discard them.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('admin.homepage.discard') }}" onsubmit="return confirm('Discard all staged draft modifications?')">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-rose-700 bg-white border border-rose-200 hover:bg-rose-50 transition-colors">
                            Discard Draft
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.homepage.publish') }}">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 shadow-xs transition-colors">
                            Publish Draft to Live
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.homepage.update') }}" class="space-y-8" id="homepage-form">
            @csrf
            @method('PUT')

            @php
                $isDraft = !empty($settings['homepage.draft_exists']);
                $val = fn($key, $default = '') => old($key, $isDraft ? ($settings['homepage.draft.'.$key] ?? $settings['homepage.'.$key] ?? $default) : ($settings['homepage.'.$key] ?? $default));
            @endphp

            {{-- 1. Top Announcement Bar --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-[#202820]/10">
                    <div>
                        <h2 class="text-base font-bold text-[#173F32]">Top Announcement Bar</h2>
                        <p class="text-xs text-[#69736B]">Highlighted notice at the very top of the page.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="announcement_enabled" value="1" {{ ($isDraft ? ($settings['homepage.draft.announcement_enabled'] ?? $settings['homepage.announcement_enabled'] ?? true) : ($settings['homepage.announcement_enabled'] ?? true)) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#245C45]"></div>
                        <span class="ml-2 text-xs font-semibold text-[#202820]">Enable Bar</span>
                    </label>
                </div>

                <div>
                    <label for="announcement_text" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Announcement Copy</label>
                    <input
                        type="text"
                        name="announcement_text"
                        id="announcement_text"
                        value="{{ $val('announcement_text', 'Connecting ethical breeders, local shelters, and loving families across the community.') }}"
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                        required
                    >
                    @error('announcement_text') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- 2. Hero Section Content --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-6">
                <h2 class="text-base font-bold text-[#173F32] pb-3 border-b border-[#202820]/10">Hero Section Messaging &amp; CTAs</h2>

                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="hero_headline" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Hero Headline</label>
                        <input
                            type="text"
                            name="hero_headline"
                            id="hero_headline"
                            value="{{ $val('hero_headline', 'Your next best friend is waiting.') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                            required
                        >
                        @error('hero_headline') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="hero_supporting" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Supporting Paragraph</label>
                        <textarea
                            name="hero_supporting"
                            id="hero_supporting"
                            rows="3"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                            required
                        >{{ $val('hero_supporting', 'Discover pets, shop food and accessories, and help animals find loving homes—all in one place.') }}</textarea>
                        @error('hero_supporting') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- CTAs --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                    {{-- CTA 1 --}}
                    <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 space-y-3">
                        <span class="block text-xs font-bold text-[#173F32] uppercase tracking-wider">Primary CTA (Marketplace)</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Label</label>
                            <input type="text" name="cta_primary_label" value="{{ $val('cta_primary_label', 'Explore Pets') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Target Link</label>
                            <input type="text" name="cta_primary_url" value="{{ $val('cta_primary_url', '#find-pets') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                    </div>

                    {{-- CTA 2 --}}
                    <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 space-y-3">
                        <span class="block text-xs font-bold text-[#173F32] uppercase tracking-wider">Secondary CTA (Pet Shop)</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Label</label>
                            <input type="text" name="cta_secondary_label" value="{{ $val('cta_secondary_label', 'Shop Pet Supplies') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Target Link</label>
                            <input type="text" name="cta_secondary_url" value="{{ $val('cta_secondary_url', '#pet-shop') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                    </div>

                    {{-- CTA 3 --}}
                    <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 space-y-3">
                        <span class="block text-xs font-bold text-[#173F32] uppercase tracking-wider">Adoption CTA (Rescue)</span>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Label</label>
                            <input type="text" name="cta_adoption_label" value="{{ $val('cta_adoption_label', 'Explore Adoption') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-[#69736B] mb-1">Target Link</label>
                            <input type="text" name="cta_adoption_url" value="{{ $val('cta_adoption_url', '#adoption-rescue') }}" class="w-full px-3 py-2 rounded-lg border border-[#202820]/15 text-xs bg-white focus:outline-none" required>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Public Sections Visibility --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-6">
                <div>
                    <h2 class="text-base font-bold text-[#173F32]">Public Section Visibility Controls</h2>
                    <p class="text-xs text-[#69736B] mt-0.5">Enable or disable major content areas on the homepage as inventory and community programs scale.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    {{-- Pet Marketplace --}}
                    <label class="flex items-center justify-between p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 cursor-pointer hover:bg-white transition-colors">
                        <div>
                            <span class="block text-xs font-bold text-[#173F32]">Pet Marketplace Grid</span>
                            <span class="text-[11px] text-[#69736B]">Live pet listings section</span>
                        </div>
                        <input type="checkbox" name="section_marketplace_visible" value="1" {{ ($isDraft ? ($settings['homepage.draft.section_marketplace_visible'] ?? $settings['homepage.section_marketplace_visible'] ?? true) : ($settings['homepage.section_marketplace_visible'] ?? true)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]">
                    </label>

                    {{-- Pet Supplies Shop --}}
                    <label class="flex items-center justify-between p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 cursor-pointer hover:bg-white transition-colors">
                        <div>
                            <span class="block text-xs font-bold text-[#173F32]">Pet Supplies Store</span>
                            <span class="text-[11px] text-[#69736B]">Single-shop products showcase</span>
                        </div>
                        <input type="checkbox" name="section_shop_visible" value="1" {{ ($isDraft ? ($settings['homepage.draft.section_shop_visible'] ?? $settings['homepage.section_shop_visible'] ?? true) : ($settings['homepage.section_shop_visible'] ?? true)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]">
                    </label>

                    {{-- Adoption & Rescue --}}
                    <label class="flex items-center justify-between p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 cursor-pointer hover:bg-white transition-colors">
                        <div>
                            <span class="block text-xs font-bold text-[#173F32]">Adoption &amp; Rescue Mission</span>
                            <span class="text-[11px] text-[#69736B]">Shelters &amp; rehoming network</span>
                        </div>
                        <input type="checkbox" name="section_adoption_visible" value="1" {{ ($isDraft ? ($settings['homepage.draft.section_adoption_visible'] ?? $settings['homepage.section_adoption_visible'] ?? true) : ($settings['homepage.section_adoption_visible'] ?? true)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]">
                    </label>

                    {{-- Breed Encyclopedia --}}
                    <label class="flex items-center justify-between p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 cursor-pointer hover:bg-white transition-colors">
                        <div>
                            <span class="block text-xs font-bold text-[#173F32]">Breed Directory Teaser</span>
                            <span class="text-[11px] text-[#69736B]">Curated breed profiles</span>
                        </div>
                        <input type="checkbox" name="section_breeds_visible" value="1" {{ ($isDraft ? ($settings['homepage.draft.section_breeds_visible'] ?? $settings['homepage.section_breeds_visible'] ?? true) : ($settings['homepage.section_breeds_visible'] ?? true)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]">
                    </label>

                    {{-- Trust & Safety --}}
                    <label class="flex items-center justify-between p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 cursor-pointer hover:bg-white transition-colors">
                        <div>
                            <span class="block text-xs font-bold text-[#173F32]">Trust &amp; Safety Standards</span>
                            <span class="text-[11px] text-[#69736B]">Moderation integrity cards</span>
                        </div>
                        <input type="checkbox" name="section_trust_safety_visible" value="1" {{ ($isDraft ? ($settings['homepage.draft.section_trust_safety_visible'] ?? $settings['homepage.section_trust_safety_visible'] ?? true) : ($settings['homepage.section_trust_safety_visible'] ?? true)) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]">
                    </label>
                </div>
            </div>

            {{-- Submit Action Bar --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
                <a href="{{ route('admin.dashboard') }}" class="px-6 py-3 rounded-full text-xs font-semibold text-[#69736B] hover:text-[#202820] bg-white border border-[#202820]/15 transition-colors">
                    Back to Dashboard
                </a>
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        formaction="{{ route('admin.homepage.draft') }}"
                        class="px-6 py-3 rounded-full text-xs font-semibold text-[#173F32] bg-[#FAF8F2] hover:bg-white border border-[#202820]/15 shadow-xs transition-colors"
                    >
                        Save as Draft
                    </button>
                    <button
                        type="submit"
                        class="px-8 py-3 rounded-full text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all focus:ring-2 focus:ring-offset-2 focus:ring-[#245C45]"
                    >
                        Publish Immediately
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layouts::admin>
