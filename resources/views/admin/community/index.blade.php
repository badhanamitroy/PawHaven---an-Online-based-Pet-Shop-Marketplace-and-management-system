@extends('layouts.admin')

@section('title', 'Adoption & Rescue Hub')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Adoption &amp; Rescue Community Hub</h1>
        <p class="text-xs text-[#69736B] mt-1">Configure emergency rescue contacts, adoption guidelines, and monitor community animal welfare rehoming.</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Active Adoption Listings</span>
            <div class="mt-2 text-2xl font-bold text-[#173F32]">{{ $adoptionCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Seeking loving forever homes</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Urgent Rehome Cases</span>
            <div class="mt-2 text-2xl font-bold text-[#F28C75]">{{ $rehomeCount }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Rehoming support cases</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold text-[#69736B]">Partner Shelters</span>
            <div class="mt-2 text-2xl font-bold text-[#245C45]">{{ $settings['community.rescue_partners_count'] ?? '12' }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Verified partner rescue groups</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Settings Form --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[#173F32] mb-4">Rescue &amp; Support Settings</h2>

                <form method="POST" action="{{ route('admin.community.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="rescue_email" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Rescue Support Email <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            name="rescue_email"
                            id="rescue_email"
                            value="{{ old('rescue_email', $settings['community.rescue_email'] ?? 'rescue@pawhaven.com') }}"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                        @error('rescue_email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="rescue_hotline" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Emergency Rescue Hotline <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="rescue_hotline"
                            id="rescue_hotline"
                            value="{{ old('rescue_hotline', $settings['community.rescue_hotline'] ?? '') }}"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                        @error('rescue_hotline')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="rescue_partners_count" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Partner Shelters Count
                        </label>
                        <input
                            type="number"
                            min="0"
                            name="rescue_partners_count"
                            id="rescue_partners_count"
                            value="{{ old('rescue_partners_count', $settings['community.rescue_partners_count'] ?? '12') }}"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>

                    <div>
                        <label for="adoption_guidelines" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Adoption Guidelines &amp; Code of Ethics
                        </label>
                        <textarea
                            name="adoption_guidelines"
                            id="adoption_guidelines"
                            rows="4"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >{{ old('adoption_guidelines', $settings['community.adoption_guidelines'] ?? "Every adoption applicant must undergo identity verification and household readiness assessment. PawHaven prohibits unlawful breeding and promotes responsible lifelong pet guardianship.") }}</textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-[#245C45] py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
                    >
                        Save Settings
                    </button>
                </form>
            </div>
        </div>

        {{-- Recent Adoption & Rehome Listings --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-[#FAF8F2] px-6 py-3.5 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Recent Community Adoption Cases</h3>
                    <a href="{{ route('admin.marketplace.listings.index') }}" class="text-xs text-[#245C45] font-semibold hover:underline">
                        View All
                    </a>
                </div>

                @if($recentAdoptions->isEmpty())
                    <div class="p-8 text-center text-xs text-[#69736B]">
                        No active adoption or rehoming cases in the database.
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($recentAdoptions as $item)
                            <div class="p-4 flex items-center justify-between hover:bg-gray-50/70 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FAF8F2] text-[#245C45]">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-bold text-xs text-[#173F32] flex items-center gap-2">
                                            <span>{{ $item->title }}</span>
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.2 text-[9px] font-bold text-emerald-800 uppercase">
                                                {{ $item->listing_type->value }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-[#69736B]">
                                            {{ $item->petCategory?->name }} &bull; {{ $item->breed?->name }} &bull; {{ $item->location }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a
                                        href="{{ route('admin.marketplace.listings.show', $item) }}"
                                        class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-3 py-1 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                    >
                                        Inspect
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
