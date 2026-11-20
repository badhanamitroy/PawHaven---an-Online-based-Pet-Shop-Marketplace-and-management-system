@extends('layouts.admin')

@section('title', 'Edit Product - ' . $product->name)

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-[#69736B]">
                <a href="{{ route('admin.products.index') }}" class="hover:text-[#245C45]">Products</a>
                <span>&rsaquo;</span>
                <span>Edit: {{ Str::limit($product->name, 25) }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32] mt-1">Edit Product</h1>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.products.index') }}"
                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Back to Catalog
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-6">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Basic Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Product Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $product->name) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="product_category_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Shop Category <span class="text-red-500">*</span>
                    </label>
                    <select
                        name="product_category_id"
                        id="product_category_id"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('product_category_id', $product->product_category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_category_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="branch_id" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Inventory Branch
                    </label>
                    <select
                        name="branch_id"
                        id="branch_id"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                    >
                        <option value="" {{ old('branch_id', $product->branch_id) === null ? 'selected' : '' }}>Company-wide inventory</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id', $product->branch_id) == $branch->id ? 'selected' : '' }}>
                                {{ $branch->company->name ?? 'Company' }} — {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sku" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Stock Keeping Unit (SKU) <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        value="{{ old('sku', $product->sku) }}"
                        required
                        class="w-full uppercase rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] font-mono focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('sku')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Detailed Description
                    </label>
                    <textarea
                        name="description"
                        id="description"
                        rows="4"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-6">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Pricing &amp; Inventory</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="price" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Regular Price ({{ $product->currency }}) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">$</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            id="price"
                            value="{{ old('price', number_format($product->price_minor / 100, 2, '.', '')) }}"
                            required
                            class="w-full rounded-xl border border-gray-200 pl-7 pr-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                    @error('price')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sale_price" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Sale / Discounted Price ({{ $product->currency }}) <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">$</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="sale_price"
                            id="sale_price"
                            value="{{ old('sale_price', $product->sale_price_minor ? number_format($product->sale_price_minor / 100, 2, '.', '') : '') }}"
                            placeholder="Leave blank for regular price"
                            class="w-full rounded-xl border border-gray-200 pl-7 pr-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                    @error('sale_price')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="stock_quantity" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        In-Stock Quantity <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="0"
                        max="2147483647"
                        name="stock_quantity"
                        id="stock_quantity"
                        value="{{ old('stock_quantity', $product->stock_quantity) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('stock_quantity')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <label for="stock_adjustment_reason" class="mt-3 block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Stock Change Reason
                    </label>
                    <input
                        type="text"
                        name="stock_adjustment_reason"
                        id="stock_adjustment_reason"
                        value="{{ old('stock_adjustment_reason') }}"
                        maxlength="1000"
                        placeholder="Required when changing stock quantity"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('stock_adjustment_reason')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="low_stock_threshold" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Low Stock Alert Threshold <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="0"
                        name="low_stock_threshold"
                        id="low_stock_threshold"
                        value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('low_stock_threshold')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-6">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Media &amp; Additional Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Current Product Photo
                    </label>
                    <div class="flex items-center gap-4">
                        @if($product->image_path)
                            <img src="{{ $product->image_path }}" alt="{{ $product->name }}" class="h-20 w-20 rounded-xl object-cover border border-gray-200 shadow-sm" />
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-xl bg-gray-100 text-gray-400 border border-gray-200">
                                <span class="text-xs">No image</span>
                            </div>
                        @endif
                        <div class="flex-1">
                            <input
                                type="file"
                                name="image"
                                id="image"
                                accept="image/png,image/jpeg,image/webp"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] file:mr-3 file:rounded-lg file:border-0 file:bg-[#245C45] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-[#173F32]"
                            />
                            <p class="mt-1 text-[11px] text-gray-500">Select a new image to replace the existing one.</p>
                        </div>
                    </div>
                    @error('image')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ingredients" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Ingredients / Composition <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <textarea
                        name="ingredients"
                        id="ingredients"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('ingredients', $product->ingredients) }}</textarea>
                </div>

                <div>
                    <label for="storage_instructions" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Storage / Care Instructions <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <textarea
                        name="storage_instructions"
                        id="storage_instructions"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('storage_instructions', $product->storage_instructions) }}</textarea>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 flex flex-col sm:flex-row gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published', $product->is_published) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                    />
                    <span class="text-xs font-semibold text-[#173F32]">Live in shop catalog</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                    />
                    <span class="text-xs font-semibold text-[#173F32]">Featured on homepage</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a
                href="{{ route('admin.products.index') }}"
                class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Cancel
            </a>
            <button
                type="submit"
                class="rounded-xl bg-[#245C45] px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
            >
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
