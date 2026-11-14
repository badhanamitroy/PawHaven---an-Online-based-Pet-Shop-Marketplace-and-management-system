@extends('layouts.admin')

@section('title', 'Shop Categories')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Shop Categories</h1>
        <p class="text-xs text-[#69736B] mt-1">Organize products in the PawHaven single shop (Food, Toys, Treats, Health &amp; Grooming, Accessories).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Add Category Form --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-[#173F32] mb-4">Add New Category</h2>

                <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="company_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Company <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="company_id"
                            id="company_id"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                        >
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id', $companies->count() === 1 ? $companies->first()->id : '') == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
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
                            placeholder="e.g. Pet Beds &amp; Blankets"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Description
                        </label>
                        <textarea
                            name="description"
                            id="description"
                            rows="2"
                            placeholder="Brief description for customers"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label for="order" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1">
                            Display Sort Order
                        </label>
                        <input
                            type="number"
                            min="0"
                            name="order"
                            id="order"
                            value="{{ old('order', 0) }}"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                {{ old('is_active', '1') ? 'checked' : '' }}
                                class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                            />
                            <span class="text-xs font-semibold text-[#173F32]">Active in shop navigation</span>
                        </label>
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
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32]">Existing Categories</h3>
                    <span class="text-xs text-[#69736B]">{{ $categories->count() }} total</span>
                </div>

                @if($categories->isEmpty())
                    <div class="p-8 text-center text-xs text-[#69736B]">
                        No shop categories created yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-gray-100 bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                                <tr>
                                    <th class="py-3 pl-6 pr-3">Category</th>
                                    <th class="px-3 py-3">Slug</th>
                                    <th class="px-3 py-3 text-center">Products</th>
                                    <th class="px-3 py-3 text-center">Order</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="py-3 pl-3 pr-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($categories as $cat)
                                    <tr class="hover:bg-gray-50/70 transition-colors">
                                        <td class="py-3 pl-6 pr-3">
                                            <div class="font-bold text-[#173F32]">{{ $cat->name }}</div>
                                            <div class="text-[11px] text-[#69736B]">{{ $cat->company->name ?? 'Company unavailable' }}</div>
                                            @if($cat->description)
                                                <div class="text-[11px] text-[#69736B] line-clamp-1">{{ $cat->description }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 font-mono text-[11px] text-gray-500">{{ $cat->slug }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <a
                                                href="{{ route('admin.products.index', ['category' => $cat->id]) }}"
                                                class="inline-flex items-center rounded-full bg-[#FAF8F2] px-2.5 py-0.5 text-[11px] font-bold text-[#245C45] hover:bg-[#245C45] hover:text-white transition-colors"
                                            >
                                                {{ $cat->products_count }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-3 text-center text-gray-600 font-mono">{{ $cat->order }}</td>
                                        <td class="px-3 py-3">
                                            @if($cat->is_active)
                                                <span class="rounded-md bg-green-50 px-2 py-0.5 text-[10px] font-bold text-green-700 ring-1 ring-inset ring-green-600/20">Active</span>
                                            @else
                                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pl-3 pr-6 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.categories.destroy', $cat) }}"
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
                                            </div>
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
