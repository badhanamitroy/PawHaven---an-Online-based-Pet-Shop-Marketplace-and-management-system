<x-layouts::admin title="Brand Identity" breadcrumbs="Website & Branding / Brand Identity">
    <div class="space-y-8" x-data="{
        brandName: '{{ addslashes($settings['brand.name'] ?? 'PawHaven') }}',
        brandTagline: '{{ addslashes($settings['brand.tagline'] ?? 'Find Pets. Give Hope. Shop with Love.') }}',
        primaryColor: '{{ $settings['brand.primary_color'] ?? '#245C45' }}',
        secondaryColor: '{{ $settings['brand.secondary_color'] ?? '#173F32' }}',
        accentColor: '{{ $settings['brand.accent_color'] ?? '#F28C75' }}',
        logoLightPreview: '{{ $settings['brand.logo_light_path'] ?? '/images/pawhaven-logo-green.png' }}',
        logoDarkPreview: '{{ $settings['brand.logo_dark_path'] ?? '/images/pawhaven-logo-white.png' }}'
    }">
        {{-- Header Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Customization</span>
                <h1 class="text-2xl font-bold tracking-tight text-[#173F32] mt-1">Brand Identity &amp; Visual Mark</h1>
                <p class="text-xs sm:text-sm text-[#69736B] mt-1">Configure your company name, logo assets, brand colors, and contact info without touching source code.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    Live Across Website
                </span>
            </div>
        </div>

        {{-- Live Brand Contrast Preview Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Preview on Light Background --}}
            <div class="bg-white rounded-3xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#69736B]">Light Background Preview (Header &amp; Public Cards)</span>
                    <div class="mt-4 p-6 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 flex items-center gap-4">
                        <img :src="logoLightPreview" alt="Light logo preview" class="h-12 w-auto object-contain">
                        <div>
                            <span class="text-xl font-bold tracking-tight text-[#173F32] block leading-none" x-text="brandName"></span>
                            <span class="text-[11px] font-medium text-[#69736B] block mt-1" x-text="brandTagline"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs text-[#69736B]">
                    <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: primaryColor }"></span>
                    <span>Primary Color: <strong x-text="primaryColor"></strong></span>
                </div>
            </div>

            {{-- Preview on Dark Background --}}
            <div class="bg-white rounded-3xl p-6 border border-[#202820]/10 shadow-xs flex flex-col justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#69736B]">Dark Background Preview (Admin Sidebar &amp; Footer)</span>
                    <div class="mt-4 p-6 rounded-2xl bg-[#173F32] border border-white/10 flex items-center gap-4">
                        <img :src="logoDarkPreview" alt="Dark logo preview" class="h-12 w-auto object-contain">
                        <div>
                            <span class="text-xl font-bold tracking-tight text-white block leading-none" x-text="brandName"></span>
                            <span class="text-[11px] font-medium text-[#FAF8F2]/80 block mt-1" x-text="brandTagline"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs text-[#69736B]">
                    <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: secondaryColor }"></span>
                    <span>Secondary Color: <strong x-text="secondaryColor"></strong></span>
                </div>
            </div>
        </div>

        {{-- Configuration Form --}}
        <form method="POST" action="{{ route('admin.brand.update') }}" enctype="multipart/form-data" class="space-y-8">
            @csrf
            @method('PUT')

            {{-- 1. General Identity --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-6">
                <h2 class="text-base font-bold text-[#173F32] pb-3 border-b border-[#202820]/10">Company &amp; Brand Details</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="brand_name" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Company / Brand Name</label>
                        <input
                            type="text"
                            name="brand_name"
                            id="brand_name"
                            x-model="brandName"
                            value="{{ old('brand_name', $settings['brand.name'] ?? 'PawHaven') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none transition-all"
                            required
                        >
                        @error('brand_name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="brand_tagline" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Tagline / Slogan</label>
                        <input
                            type="text"
                            name="brand_tagline"
                            id="brand_tagline"
                            x-model="brandTagline"
                            value="{{ old('brand_tagline', $settings['brand.tagline'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none transition-all"
                            required
                        >
                        @error('brand_tagline') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Color Tokens --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-2">
                    <div>
                        <label for="brand_primary_color" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Primary Color</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                name="brand_primary_color"
                                id="brand_primary_color"
                                x-model="primaryColor"
                                class="h-10 w-12 rounded-lg border border-[#202820]/15 cursor-pointer p-0.5 bg-white"
                            >
                            <input
                                type="text"
                                x-model="primaryColor"
                                class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm font-mono uppercase"
                            >
                        </div>
                        @error('brand_primary_color') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="brand_secondary_color" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Secondary Color</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                name="brand_secondary_color"
                                id="brand_secondary_color"
                                x-model="secondaryColor"
                                class="h-10 w-12 rounded-lg border border-[#202820]/15 cursor-pointer p-0.5 bg-white"
                            >
                            <input
                                type="text"
                                x-model="secondaryColor"
                                class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm font-mono uppercase"
                            >
                        </div>
                        @error('brand_secondary_color') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="brand_accent_color" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Accent Color</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                name="brand_accent_color"
                                id="brand_accent_color"
                                x-model="accentColor"
                                class="h-10 w-12 rounded-lg border border-[#202820]/15 cursor-pointer p-0.5 bg-white"
                            >
                            <input
                                type="text"
                                x-model="accentColor"
                                class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm font-mono uppercase"
                            >
                        </div>
                        @error('brand_accent_color') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 2. Logo Uploads --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-6">
                <h2 class="text-base font-bold text-[#173F32] pb-3 border-b border-[#202820]/10">Logo &amp; Icon Assets</h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {{-- Light Logo --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Primary Logo (Light Background)</label>
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-center mb-3">
                            <img :src="logoLightPreview" alt="Light logo preview" class="h-12 w-auto mx-auto object-contain">
                        </div>
                        <input
                            type="file"
                            name="logo_light"
                            accept="image/png,image/jpeg,image/webp"
                            @change="const file = $event.target.files[0]; if (file) logoLightPreview = URL.createObjectURL(file);"
                            class="block w-full text-xs text-[#69736B] file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#245C45] file:text-white hover:file:bg-[#173F32]"
                        >
                        <p class="text-[11px] text-[#69736B] mt-1">PNG, JPG, or WebP. Max 2MB (max 3000x3000px).</p>
                        @error('logo_light') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Dark Logo --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Dark Logo (Dark Background)</label>
                        <div class="p-4 rounded-2xl bg-[#173F32] border border-white/10 text-center mb-3">
                            <img :src="logoDarkPreview" alt="Dark logo preview" class="h-12 w-auto mx-auto object-contain">
                        </div>
                        <input
                            type="file"
                            name="logo_dark"
                            accept="image/png,image/jpeg,image/webp"
                            @change="const file = $event.target.files[0]; if (file) logoDarkPreview = URL.createObjectURL(file);"
                            class="block w-full text-xs text-[#69736B] file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#245C45] file:text-white hover:file:bg-[#173F32]"
                        >
                        <p class="text-[11px] text-[#69736B] mt-1">White or high-contrast mark for dark surfaces. PNG, JPG, or WebP. Max 2MB.</p>
                        @error('logo_dark') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Favicon --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Browser Favicon</label>
                        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-center mb-3 flex items-center justify-center h-20">
                            <img src="{{ $settings['brand.favicon_path'] ?? '/favicon.ico' }}" alt="Favicon" class="h-8 w-8 object-contain">
                        </div>
                        <input
                            type="file"
                            name="favicon"
                            accept="image/x-icon,image/png"
                            class="block w-full text-xs text-[#69736B] file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#245C45] file:text-white hover:file:bg-[#173F32]"
                        >
                        <p class="text-[11px] text-[#69736B] mt-1">.ico or PNG icon. Max 512KB.</p>
                        @error('favicon') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 3. Contact & Social Information --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-6">
                <h2 class="text-base font-bold text-[#173F32] pb-3 border-b border-[#202820]/10">Contact &amp; Public Social Links</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="brand_contact_email" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Customer Support Email</label>
                        <input
                            type="email"
                            name="brand_contact_email"
                            id="brand_contact_email"
                            value="{{ old('brand_contact_email', $settings['brand.contact_email'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                            required
                        >
                        @error('brand_contact_email') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="brand_phone" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Support Phone Number</label>
                        <input
                            type="text"
                            name="brand_phone"
                            id="brand_phone"
                            value="{{ old('brand_phone', $settings['brand.phone'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                        >
                        @error('brand_phone') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="brand_address" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Physical Business Address</label>
                    <input
                        type="text"
                        name="brand_address"
                        id="brand_address"
                        value="{{ old('brand_address', $settings['brand.address'] ?? '') }}"
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                    >
                    @error('brand_address') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-2">
                    <div>
                        <label for="brand_social_facebook" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Facebook URL</label>
                        <input
                            type="url"
                            name="brand_social_facebook"
                            id="brand_social_facebook"
                            value="{{ old('brand_social_facebook', $settings['brand.social_facebook'] ?? '') }}"
                            placeholder="https://facebook.com/..."
                            class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-xs text-[#202820]"
                        >
                    </div>

                    <div>
                        <label for="brand_social_instagram" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Instagram URL</label>
                        <input
                            type="url"
                            name="brand_social_instagram"
                            id="brand_social_instagram"
                            value="{{ old('brand_social_instagram', $settings['brand.social_instagram'] ?? '') }}"
                            placeholder="https://instagram.com/..."
                            class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-xs text-[#202820]"
                        >
                    </div>

                    <div>
                        <label for="brand_social_x" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">X / Twitter URL</label>
                        <input
                            type="url"
                            name="brand_social_x"
                            id="brand_social_x"
                            value="{{ old('brand_social_x', $settings['brand.social_x'] ?? '') }}"
                            placeholder="https://x.com/..."
                            class="w-full px-3 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-xs text-[#202820]"
                        >
                    </div>
                </div>
            </div>

            {{-- 4. SEO & Metadata --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#202820]/10 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-[#173F32] pb-3 border-b border-[#202820]/10">Search Engine &amp; Social Metadata</h2>
                <div>
                    <label for="brand_meta_description" class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-2">Meta Description (SEO)</label>
                    <textarea
                        name="brand_meta_description"
                        id="brand_meta_description"
                        rows="3"
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                    >{{ old('brand_meta_description', $settings['brand.meta_description'] ?? '') }}</textarea>
                    @error('brand_meta_description') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Submit Action Bar --}}
            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('admin.dashboard') }}" class="px-6 py-3 rounded-full text-xs font-semibold text-[#69736B] hover:text-[#202820] bg-white border border-[#202820]/15 transition-colors">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-8 py-3 rounded-full text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all focus:ring-2 focus:ring-offset-2 focus:ring-[#245C45]"
                >
                    Save Brand Settings
                </button>
            </div>
        </form>
    </div>
</x-layouts::admin>
