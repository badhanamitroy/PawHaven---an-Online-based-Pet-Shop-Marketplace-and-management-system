@extends('layouts.admin')

@section('title', 'Products Catalog')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">PawHaven Pet Shop &bull; Products</h1>
            @can('create', \App\Models\Product::class)
                <p class="text-xs text-[#69736B] mt-1">Manage single-shop inventory, pricing, stock levels, and store visibility.</p>
            @else
                <p class="text-xs text-[#69736B] mt-1">View products and availability for your assigned branch.</p>
            @endcan
        </div>
        @can('create', \App\Models\Product::class)
        <div>
            <a
                href="{{ route('admin.products.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-[#245C45] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add Product</span>
            </a>
        </div>
        @endcan
    </div>

    {{-- Filter Bar --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.products.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search name or SKU..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] placeholder-gray-400 focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div>
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
            <div>
                <select
                    name="stock_status"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Stock Levels</option>
                    <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                    <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock Alerts</option>
                    <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category', 'stock_status']))
                    <a
                        href="{{ route('admin.products.index') }}"
                        class="text-xs text-[#69736B] hover:text-[#202820] underline"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Products Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($products->isEmpty())
            <div class="p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#FAF8F2] text-[#245C45]">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-bold text-[#173F32]">No products found</h3>
                <p class="mt-1 text-xs text-[#69736B]">There are no products matching your search criteria or filter options.</p>
                @can('create', \App\Models\Product::class)
                <div class="mt-4">
                    <a href="{{ route('admin.products.create') }}" class="text-xs font-semibold text-[#245C45] hover:underline">+ Add the first product</a>
                </div>
                @endcan
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Product</th>
                            <th class="px-3 py-3.5">Category</th>
                            <th class="px-3 py-3.5">Price</th>
                            <th class="px-3 py-3.5">Stock</th>
                            <th class="px-3 py-3.5">Status</th>
                            @can('create', \App\Models\Product::class)
                                <th class="py-3.5 pl-3 pr-6 text-right">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($products as $product)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3 pl-6 pr-3">
                                    <div class="flex items-center gap-3">
                                        @if($product->image_path)
                                            <img src="{{ $product->image_path }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg object-cover border border-gray-200" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-[#69736B] border border-gray-200">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-[#173F32] flex items-center gap-1.5">
                                                <span>{{ $product->name }}</span>
                                                @if($product->is_featured)
                                                    <span class="rounded bg-[#F28C75]/20 px-1.5 py-0.5 text-[9px] font-bold text-[#b44e37]">Featured</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-[#69736B] font-mono">SKU: {{ $product->sku }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-[#202820]">
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-medium text-gray-700">
                                        {{ $product->category->name }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-[#202820]">
                                    @if($product->sale_price_minor)
                                        <div class="font-bold text-[#245C45]">{{ $product->currency }} {{ number_format($product->sale_price_minor / 100, 2) }}</div>
                                        <div class="text-[10px] text-gray-400 line-through">{{ $product->currency }} {{ number_format($product->price_minor / 100, 2) }}</div>
                                    @else
                                        <div class="font-bold text-[#202820]">{{ $product->currency }} {{ number_format($product->price_minor / 100, 2) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($product->stock_quantity <= 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-800">
                                            Out of Stock (0)
                                        </span>
                                    @elseif($product->isLowStock())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                            Low: {{ $product->stock_quantity }} left
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                            In Stock ({{ $product->stock_quantity }})
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($product->archived_at !== null)
                                        <span class="inline-flex items-center rounded-md bg-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-700">Archived</span>
                                    @elseif($product->is_published)
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-[11px] font-semibold text-green-700 ring-1 ring-inset ring-green-600/20">Live</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 ring-1 ring-inset ring-gray-500/20">Unpublished</span>
                                    @endif
                                </td>
                                @can('create', \App\Models\Product::class)
                                <td class="py-3 pl-3 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($product->archived_at === null)
                                        <form method="POST" action="{{ route('admin.products.archive', $product) }}" onsubmit="return confirm('Archive this product? Existing order and stock history will be retained.');">
                                            @csrf
                                            <button class="rounded-lg border border-amber-300 px-2 py-1 text-[10px] font-semibold text-amber-800">Archive</button>
                                        </form>
                                        @endif
                                        @can('update', $product)
                                        <a
                                            href="{{ route('admin.products.edit', $product) }}"
                                            class="rounded-lg p-1 text-gray-500 hover:bg-gray-100 hover:text-[#245C45] transition-colors"
                                            title="Edit"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        @endcan
                                        @can('delete', $product)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.products.destroy', $product) }}"
                                            onsubmit="return confirm('Are you sure you want to delete this product?');"
                                            class="inline"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                title="Delete"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

