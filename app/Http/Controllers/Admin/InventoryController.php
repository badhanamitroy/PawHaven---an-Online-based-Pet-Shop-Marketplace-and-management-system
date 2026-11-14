<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->activeMemberships($request->user())->contains(fn ($membership): bool =>
                in_array($membership->role, ['owner', 'manager'], true)
            ), 403);

        $query = Product::with('category');

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'low_stock') {
                $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->where('stock_quantity', '>', 0);
            } elseif ($filter === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($filter === 'in_stock') {
                $query->whereColumn('stock_quantity', '>', 'low_stock_threshold');
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('stock_quantity', 'asc')->paginate(20)->withQueryString();

        $lowStockTotal = Product::lowStock()->count();
        $outOfStockTotal = Product::where('stock_quantity', '<=', 0)->count();
        $totalItemsInStock = Product::sum('stock_quantity');

        return view('admin.inventory.index', compact('products', 'lowStockTotal', 'outOfStockTotal', 'totalItemsInStock'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->canManageInventory($request->user(), $product->company_id), 403);
        abort_unless(app(\App\Services\BusinessAccessService::class)
            ->canAccessRecord($request->user(), $product->company_id, $product->branch_id), 403);

        $validated = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        $oldStock = $product->stock_quantity;
        $product->update([
            'stock_quantity' => (int) $validated['stock_quantity'],
            'low_stock_threshold' => (int) $validated['low_stock_threshold'],
        ]);

        AdminAuditLog::record(
            'inventory.adjust',
            "Adjusted stock for '{$product->name}' (SKU: {$product->sku}) from {$oldStock} to {$validated['stock_quantity']}.",
            [
                'product_id' => $product->id,
                'old_stock' => $oldStock,
                'new_stock' => (int) $validated['stock_quantity'],
            ]
        );

        return redirect()->route('admin.inventory.index')->with('success', "Stock for '{$product->name}' updated to {$validated['stock_quantity']}.");
    }
}
