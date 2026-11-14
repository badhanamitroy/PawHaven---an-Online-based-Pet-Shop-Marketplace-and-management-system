@extends('layouts.admin')

@section('title', 'Pet Categories')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Pet Marketplace Categories</h1>
        <p class="text-xs text-[#69736B] mt-1">Species classifications for pet adoption and marketplace listings (Dogs, Cats, Birds, Small Animals, etc.).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Add Category Form --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[#173F32] mb-4">Add Pet Category</h2>

                <form method="POST" action="{{ route('admin.marketplace.categories.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="name" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Category Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name') }}"
                            required
                            placeholder="e.g. Reptiles &amp; Amphibians"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-[#245C45] py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
                    >
                        Save Category
                    </button>
                </form>
            </div>
        </div>

        {{-- Categories Table --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-[#FAF8F2] px-6 py-3.5 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Active Pet Categories</h3>
                    <span class="text-xs text-[#69736B]">{{ $categories->count() }} total</span>
                </div>

                @if($categories->isEmpty())
                    <div class="p-8 text-center text-xs text-[#69736B]">
                        No pet categories defined yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-gray-100 bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                                <tr>
                                    <th class="py-3 pl-6 pr-3">Category Name</th>
                                    <th class="px-3 py-3">Slug</th>
                                    <th class="px-3 py-3 text-center">Breeds</th>
                                    <th class="px-3 py-3 text-center">Pet Listings</th>
                                    <th class="py-3 pl-3 pr-6 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($categories as $cat)
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="py-3 pl-6 pr-3 font-bold text-[#173F32]">{{ $cat->name }}</td>
                                        <td class="px-3 py-3 font-mono text-[11px] text-gray-500">{{ $cat->slug }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <a
                                                href="{{ route('admin.breeds.index', ['category' => $cat->id]) }}"
                                                class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-[#245C45] hover:text-white transition-colors"
                                            >
                                                {{ $cat->breeds_count }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <a
                                                href="{{ route('admin.marketplace.listings.index', ['category' => $cat->id]) }}"
                                                class="rounded-full bg-[#FAF8F2] px-2.5 py-0.5 text-[11px] font-bold text-[#245C45] hover:bg-[#245C45] hover:text-white transition-colors"
                                            >
                                                {{ $cat->pet_listings_count }}
                                            </a>
                                        </td>
                                        <td class="py-3 pl-3 pr-6 text-right">
                                            <form
                                                method="POST"
                                                action="{{ route('admin.marketplace.categories.destroy', $cat) }}"
                                                onsubmit="return confirm('Delete category \'{{ $cat->name }}\'?');"
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
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

