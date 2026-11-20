<x-layouts::app :title="__('Edit Pet Listing')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 max-w-4xl mx-auto">
        {{-- Flash / Error Messages --}}
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
                <p class="font-bold">Please check the form for errors:</p>
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Rejection Alert Banner if Rejected --}}
        @if($listing->status === \App\Enums\PetListingStatus::Rejected && $listing->moderationActions->isNotEmpty())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-900 shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-rose-900">This Listing Requires Changes Before Approval</h3>
                        <p class="text-xs text-rose-800 mt-1">
                            <strong>Administrator Feedback:</strong> {{ $listing->moderationActions->first()->note ?? 'Please update information to comply with animal welfare standards.' }}
                        </p>
                        <p class="text-xs text-rose-700 mt-2">
                            Update the fields below and click <strong>"Resubmit for Review"</strong> to send it back to the moderation queue.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Header --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="inline-flex items-center gap-2 text-xs font-semibold text-[#69736B] uppercase tracking-wider">
                <a href="{{ route('seller.dashboard') }}" class="hover:underline">Seller Dashboard</a>
                <span>&bull;</span>
                <a href="{{ route('seller.listings.index') }}" class="hover:underline">My Listings</a>
                <span>&bull;</span>
                <span>Edit #{{ $listing->id }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-1">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32]">
                        Edit: {{ $listing->title }}
                    </h1>
                    <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                        Current Status: <strong class="uppercase font-semibold text-[#202820]">{{ str_replace('_', ' ', $listing->status->value) }}</strong>
                    </p>
                </div>
                @if($listing->status === \App\Enums\PetListingStatus::Published)
                    <a href="{{ route('listings.show', $listing) }}" target="_blank" class="text-xs font-semibold text-[#245C45] hover:underline shrink-0">
                        View Public Page &rarr;
                    </a>
                @endif
            </div>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('seller.listings.update', $listing) }}" enctype="multipart/form-data" class="space-y-6" x-data="{ type: '{{ old('listing_type', $listing->listing_type->value) }}' }">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
                {{-- Listing Title --}}
                <div>
                    <label for="title" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Listing Title <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        value="{{ old('title', $listing->title) }}"
                        required
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                    >
                    @error('title') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Category & Breed --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="pet_category_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Pet Category
                        </label>
                        <select
                            name="pet_category_id"
                            id="pet_category_id"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                        >
                            <option value="">Select Category...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('pet_category_id', $listing->pet_category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('pet_category_id') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="breed_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Breed
                        </label>
                        <select
                            name="breed_id"
                            id="breed_id"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                        >
                            <option value="">Select Breed (Optional / Mixed)...</option>
                            @foreach($breeds as $breed)
                                <option value="{{ $breed->id }}" {{ old('breed_id', $listing->breed_id) == $breed->id ? 'selected' : '' }}>
                                    {{ $breed->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('breed_id') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Listing Type & Price --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="listing_type" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Listing Type <span class="text-rose-500">*</span>
                        </label>
                        <select
                            name="listing_type"
                            id="listing_type"
                            x-model="type"
                            required
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                        >
                            <option value="sale">Commercial Sale</option>
                            <option value="adoption">Adoption / Rehoming (Free)</option>
                            <option value="rescue">Shelter Rescue</option>
                            <option value="rehoming">Gentle Rehoming</option>
                        </select>
                        @error('listing_type') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="price" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Price ({{ $listing->currency }}) <span x-show="type === 'sale'" class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            id="price"
                            value="{{ old('price', $listing->price_minor ? number_format($listing->price_minor / 100, 2, '.', '') : '') }}"
                            placeholder="0.00"
                            :disabled="type !== 'sale'"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45] disabled:opacity-50"
                        >
                        <p class="text-[11px] text-[#69736B] mt-1" x-show="type !== 'sale'">Adoption &amp; rescue listings are always zero-fee.</p>
                        @error('price') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Location --}}
                <div>
                    <label for="location" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Location (City / State) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="location"
                        id="location"
                        value="{{ old('location', $listing->location) }}"
                        required
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                    >
                    @error('location') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Description &amp; Care Details <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="5"
                        required
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                    >{{ old('description', $listing->description) }}</textarea>
                    @error('description') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Existing Photos --}}
                @if($listing->images->isNotEmpty())
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-2">
                            Current Photos ({{ $listing->images->count() }}/5)
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            @foreach($listing->images as $img)
                                <div class="relative rounded-xl overflow-hidden aspect-square border border-[#202820]/10 bg-white">
                                    <img src="{{ $img->url() }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Add Additional Photos --}}
                @if($listing->images->count() < 5)
                    <div>
                        <label for="images" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Add More Photos ({{ 5 - $listing->images->count() }} remaining slots)
                        </label>
                        <input
                            type="file"
                            name="images[]"
                            id="images"
                            multiple
                            accept="image/*"
                            class="w-full px-4 py-2.5 rounded-xl border border-dashed border-[#202820]/25 bg-[#FAF8F2] text-xs focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#245C45] file:text-white hover:file:bg-[#173F32]"
                        >
                        @error('images') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('seller.listings.index') }}" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-[#69736B] hover:text-[#202820]">
                    Cancel
                </a>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        name="action"
                        value="save"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-white border border-[#202820]/15 hover:bg-[#FAF8F2] transition-colors"
                    >
                        Save Changes
                    </button>

                    @if(in_array($listing->status, [\App\Enums\PetListingStatus::Draft, \App\Enums\PetListingStatus::Rejected], true))
                        <button
                            type="submit"
                            name="action"
                            value="submit"
                            class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                        >
                            Resubmit for Review &rarr;
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</x-layouts::app>
