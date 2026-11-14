<x-layouts::app :title="__('Create Pet Listing')">
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

        {{-- Header --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="inline-flex items-center gap-2 text-xs font-semibold text-[#69736B] uppercase tracking-wider">
                <a href="{{ route('seller.dashboard') }}" class="hover:underline">Seller Dashboard</a>
                <span>&bull;</span>
                <a href="{{ route('seller.listings.index') }}" class="hover:underline">My Listings</a>
                <span>&bull;</span>
                <span>Create</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-1">
                Create Pet Listing
            </h1>
            <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                Provide accurate, ethical information regarding the pet. All listings undergo administrator review before publishing.
            </p>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('seller.listings.store') }}" enctype="multipart/form-data" class="space-y-6" x-data="{ type: '{{ old('listing_type', 'sale') }}' }">
            @csrf

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
                        value="{{ old('title') }}"
                        placeholder="e.g. Friendly 2-Year-Old Golden Retriever looking for loving home"
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
                                <option value="{{ $category->id }}" {{ old('pet_category_id') == $category->id ? 'selected' : '' }}>
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
                                <option value="{{ $breed->id }}" {{ old('breed_id') == $breed->id ? 'selected' : '' }}>
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
                            Price ($ USD) <span x-show="type === 'sale'" class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            id="price"
                            value="{{ old('price') }}"
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
                        value="{{ old('location') }}"
                        placeholder="e.g. Austin, Texas"
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
                        placeholder="Describe the pet's temperament, training, diet, habits, and any specific home environment needs..."
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                    >{{ old('description') }}</textarea>
                    @error('description') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Image Uploads --}}
                <div>
                    <label for="images" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Pet Photos (Up to 5 photos, max 2MB each)
                    </label>
                    <input
                        type="file"
                        name="images[]"
                        id="images"
                        multiple
                        accept="image/*"
                        class="w-full px-4 py-2.5 rounded-xl border border-dashed border-[#202820]/25 bg-[#FAF8F2] text-xs focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#245C45] file:text-white hover:file:bg-[#173F32]"
                    >
                    <p class="text-[11px] text-[#69736B] mt-1.5">Clear, well-lit photos increase adoption and inquiry success.</p>
                    @error('images') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>
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
                        value="draft"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-white border border-[#202820]/15 hover:bg-[#FAF8F2] transition-colors"
                    >
                        Save as Draft
                    </button>
                    <button
                        type="submit"
                        name="action"
                        value="submit"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                    >
                        Submit for Review &rarr;
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layouts::app>
