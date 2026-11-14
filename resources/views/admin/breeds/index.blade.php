@extends('layouts.admin')

@section('title', 'Breed Encyclopedia')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Breed Encyclopedia Profiles</h1>
        <p class="text-xs text-[#69736B] mt-1">Manage standard breed profiles, physical characteristics, temperament notes, and care information.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Add Breed Form --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[#173F32] mb-4">Add Breed Profile</h2>

                <form method="POST" action="{{ route('admin.breeds.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="pet_category_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Pet Category <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="pet_category_id"
                            id="pet_category_id"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('pet_category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('pet_category_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Breed Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
                            required
                            placeholder="e.g. Golden Retriever"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Overview &amp; Temperament
                        </label>
                        <textarea
                            name="description"
                            id="description"
                            rows="3"
                            placeholder="Intelligent, friendly, and devoted..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label for="care_information" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Care &amp; Exercise Needs
                        </label>
                        <textarea
                            name="care_information"
                            id="care_information"
                            rows="3"
                            placeholder="Requires daily vigorous exercise and regular grooming..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >{{ old('care_information') }}</textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-[#245C45] py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
                    >
                        Save Breed Profile
                    </button>
                </form>
            </div>
        </div>

        {{-- Breeds Table --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Filter Bar --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('admin.breeds.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search breed by name..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                    <div class="w-full sm:w-48">
                        <select
                            name="category"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                    >
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'category']))
                        <a href="{{ route('admin.breeds.index') }}" class="text-xs text-[#69736B] hover:underline self-center">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
                @if($breeds->isEmpty())
                    <div class="p-8 text-center text-xs text-[#69736B]">
                        No breed profiles found. Add the first breed using the form on the left.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                                <tr>
                                    <th class="py-3.5 pl-6 pr-3">Breed</th>
                                    <th class="px-3 py-3.5">Category</th>
                                    <th class="px-3 py-3.5 text-center">Listings</th>
                                    <th class="py-3.5 pl-3 pr-6 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($breeds as $breed)
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="py-3.5 pl-6 pr-3">
                                            <div class="font-bold text-[#173F32]">{{ $breed->name }}</div>
                                            @if($breed->description)
                                                <div class="text-[11px] text-[#69736B] line-clamp-1">{{ $breed->description }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3.5">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">
                                                {{ $breed->petCategory?->name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3.5 text-center font-bold text-[#245C45]">
                                            {{ $breed->listings_count }}
                                        </td>
                                        <td class="py-3.5 pl-3 pr-6 text-right">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.breeds.destroy', $breed) }}"
                                                onsubmit="return confirm('Delete breed \'{{ $breed->name }}\'?');"
                                                class="inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="rounded p-1 text-gray-400 hover:text-red-600 transition-colors"
                                                    title="Delete"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($breeds->hasPages())
                        <div class="border-t border-gray-100 p-4">
                            {{ $breeds->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

