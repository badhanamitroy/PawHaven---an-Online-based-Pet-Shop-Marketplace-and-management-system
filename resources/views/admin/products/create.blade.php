@extends('layouts.admin')

@section('title', 'Add New Product')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-[#69736B]">
                <a href="{{ route('admin.products.index') }}" class="hover:text-[#245C45]">Products</a>
                <span>&rsaquo;</span>
                <span>Add Product</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32] mt-1">Create New Product</h1>
        </div>
        <a
            href="{{ route('admin.products.index') }}"
            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
        >
            Cancel
        </a>
    </div>

    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

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
                        value="{{ old('name') }}"
                        required
                        placeholder="e.g. Grain-Free Salmon & Sweet Potato Kibble (5kg)"
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
                        <option value="">Select a category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('product_category_id') == $category->id ? 'selected' : '' }}>
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
                        Inventory Branch <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <select
                        name="branch_id"
                        id="branch_id"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                    >
                        <option value="">Company-wide inventory</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
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
                        value="{{ old('sku') }}"
                        required
                        placeholder="e.g. FOOD-DOG-001"
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
                        placeholder="Nutritional highlights, material composition, sizing details, etc."
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('description') }}</textarea>
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
                        Regular Price ({{ \App\Facades\Settings::get('shop.currency', 'USD') }}) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">$</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            id="price"
                            value="{{ old('price') }}"
                            required
                            placeholder="49.99"
                            class="w-full rounded-xl border border-gray-200 pl-7 pr-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                    @error('price')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sale_price" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Sale / Discounted Price ({{ \App\Facades\Settings::get('shop.currency', 'USD') }}) <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500">$</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="sale_price"
                            id="sale_price"
                            value="{{ old('sale_price') }}"
                            placeholder="39.99"
                            class="w-full rounded-xl border border-gray-200 pl-7 pr-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                        />
                    </div>
                    @error('sale_price')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="stock_quantity" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Initial Stock Quantity <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        min="0"
                        name="stock_quantity"
                        id="stock_quantity"
                        value="{{ old('stock_quantity', 25) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('stock_quantity')
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
                        value="{{ old('low_stock_threshold', 5) }}"
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
                    <label for="image" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Product Photo
                    </label>
                    <input
                        type="file"
                        name="image"
                        id="image"
                        accept="image/png,image/jpeg,image/webp"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] file:mr-3 file:rounded-lg file:border-0 file:bg-[#245C45] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-[#173F32]"
                    />
                    <p class="mt-1 text-[11px] text-gray-500">Supported formats: PNG, JPG, WebP up to 3MB.</p>
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
                        placeholder="e.g. Fresh salmon (45%), sweet potato, peas, brewer's yeast..."
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('ingredients') }}</textarea>
                </div>

                <div>
                    <label for="storage_instructions" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Storage / Care Instructions <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <textarea
                        name="storage_instructions"
                        id="storage_instructions"
                        rows="3"
                        placeholder="e.g. Store in a cool, dry place. Reseal after opening."
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('storage_instructions') }}</textarea>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 flex flex-col sm:flex-row gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published', '1') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                    />
                    <span class="text-xs font-semibold text-[#173F32]">Publish immediately in shop</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_featured"
                        value="1"
                        {{ old('is_featured') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                    />
                    <span class="text-xs font-semibold text-[#173F32]">Feature on homepage showcase</span>
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
                Create Product
            </button>
        </div>
    </form>
</div>
@endsection
